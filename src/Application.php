<?php declare(strict_types=1);

namespace Piccolo;

use Closure;
use InvalidArgumentException;
use Piccolo\Container\{ScopeFactory, ScopeInterface};
use Piccolo\Middleware\LazyLoadingMiddleware;
use Piccolo\Runtime\RunnerInterface;
use Laminas\Stratigility\Middleware\{CallableMiddlewareDecorator, RequestHandlerMiddleware};
use Laminas\Stratigility\{MiddlewarePipe, MiddlewarePipeInterface};
use Mezzio\Router\{Route, RouteCollector};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use Psr\Container\{ContainerExceptionInterface, ContainerInterface, NotFoundExceptionInterface};
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use function Laminas\Stratigility\path;

/**
 * Application
 *
 * An almost copy-carbon of Mezzio\Application class, but with the ability to inject middlewares and request handlers
 * from the container (via the method `toMiddleware()` instead of using a `MiddlewareFactoryInterface` class).
 *
 * Each request is handled in its own container scope (when the container can create scopes), given to the middlewares
 * in the ScopeInterface attribute of the request: the middlewares and handlers piped as class names are resolved from
 * it, so the scoped services live as long as the request, even in a worker handling many requests.
 *
 * A middleware is a class name (resolved from the container when a request reaches it), a closure, a
 * MiddlewareInterface or a RequestHandlerInterface.
 *
 * @see https://docs.mezzio.dev/mezzio/v3/features/application/
 *
 * @phpstan-type Middleware string|Closure|MiddlewareInterface|RequestHandlerInterface
 */
final readonly class Application implements MiddlewareInterface, RequestHandlerInterface
{

    public function __construct(
        private MiddlewarePipeInterface $pipeline,
        private RunnerInterface $runner,
        private RouteCollector $routes,
        private ContainerInterface $container
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->inScope($request, fn (ServerRequestInterface $request) => $this->pipeline->handle($request));
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->inScope(
            $request,
            fn (ServerRequestInterface $request) => $this->pipeline->process($request, $handler)
        );
    }

    /**
     * Runs the application with the runner of the environment (classic SAPI, or FrankenPHP worker).
     */
    public function run(): void
    {
        $this->runner->run($this);
    }

    /**
     * Handles the request in a new scope, disposed once the response is created (or the request failed). A request that
     * already has a scope (an application piped in another one) keeps it.
     *
     * @param callable(ServerRequestInterface): ResponseInterface $next
     */
    private function inScope(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        if (!$this->container instanceof ScopeFactory || $request->getAttribute(ScopeInterface::class) !== null) {
            return $next($request);
        }

        $scope = $this->container->createScope();

        try {
            return $next($request->withAttribute(ScopeInterface::class, $scope));
        } finally {
            $scope->dispose();
        }
    }

    /**
     * Pipes a middleware (or a list of middlewares) on every path, or only on the given one:
     *
     *  $app->pipe(ErrorHandler::class);
     *  $app->pipe('/api', [ProblemDetailsMiddleware::class, BodyParamsMiddleware::class]);
     *
     * @param Middleware|list<Middleware> $middlewareOrPath The middleware, or the path when a middleware follows.
     * @param Middleware|list<Middleware>|null $middleware
     *
     * @throws InvalidArgumentException When the path is not a string.
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function pipe(
        string|array|callable|MiddlewareInterface|RequestHandlerInterface $middlewareOrPath,
        null|string|array|callable|MiddlewareInterface|RequestHandlerInterface $middleware = null
    ): void {
        if ($middleware === null) {
            $path = '/';
            $middleware = $middlewareOrPath;
        } elseif (is_string($middlewareOrPath)) {
            $path = $middlewareOrPath;
        } else {
            throw new InvalidArgumentException(sprintf(
                'The path to pipe a middleware on must be a string, %s given',
                get_debug_type($middlewareOrPath)
            ));
        }

        if (is_array($middleware)) {
            if ($path === '/') {
                foreach ($middleware as $middle) {
                    $this->pipe($path, $middle);
                }
                return;
            }

            // Group path-scoped middleware into a single nested pipe, so the path is matched once per request
            // instead of once per middleware in the group.
            $group = new MiddlewarePipe();
            foreach ($middleware as $middle) {
                $group->pipe($this->toMiddleware($middle));
            }

            $this->pipeline->pipe(path($path, $group));
            return;
        }

        $middleware = $path !== '/'
            ? path($path, $this->toMiddleware($middleware))
            : $this->toMiddleware($middleware);

        $this->pipeline->pipe($middleware);
    }

    /**
     * @param Middleware $middleware
     */
    private function toMiddleware(
        string|callable|MiddlewareInterface|RequestHandlerInterface $middleware
    ): MiddlewareInterface {
        if (is_string($middleware) && class_exists($middleware)) {
            $middleware = new LazyLoadingMiddleware($this->container, $middleware);
        } elseif (is_callable($middleware)) {
            $middleware = new CallableMiddlewareDecorator($middleware);
        }

        if ($middleware instanceof RequestHandlerInterface) {
            return new RequestHandlerMiddleware($middleware);
        }

        if (is_string($middleware)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Middleware must be an instance of "%s" or "%s", "%s" given',
                    MiddlewareInterface::class,
                    RequestHandlerInterface::class,
                    $middleware
                )
            );
        }

        return $middleware;
    }

    /**
     * The middleware can also be an array, e.g. the middlewares of the route followed by its request handler: they are
     * grouped into a single nested pipe, only executed when the route is matched.
     *
     * @param non-empty-string $path
     * @param Middleware|list<Middleware> $middleware
     * @param list<string>|null $methods Null for any method.
     * @param non-empty-string|null $name
     *
     * @throws InvalidArgumentException If the middleware is an empty array.
     */
    public function route(
        string $path,
        string|array|callable|MiddlewareInterface|RequestHandlerInterface $middleware,
        ?array $methods = null,
        ?string $name = null
    ): Route
    {
        if (!is_array($middleware)) {
            return $this->routes->route($path, $this->toMiddleware($middleware), $methods, $name);
        }

        if ($middleware === []) {
            throw new InvalidArgumentException(sprintf('The route "%s" must have at least one middleware.', $path));
        }

        $group = new MiddlewarePipe();
        foreach ($middleware as $middle) {
            $group->pipe($this->toMiddleware($middle));
        }

        return $this->routes->route($path, $group, $methods, $name);
    }

    /**
     * @param non-empty-string $path
     * @param Middleware|list<Middleware> $middleware
     * @param non-empty-string|null $name
     */
    public function get(
        string $path,
        string|array|callable|MiddlewareInterface|RequestHandlerInterface $middleware,
        ?string $name = null
    ): Route
    {
        return $this->route($path, $middleware, ['GET'], $name);
    }

    /**
     * @param non-empty-string $path
     * @param Middleware|list<Middleware> $middleware
     * @param non-empty-string|null $name
     */
    public function post(
        string $path,
        string|array|callable|MiddlewareInterface|RequestHandlerInterface $middleware,
        ?string $name = null
    ): Route
    {
        return $this->route($path, $middleware, ['POST'], $name);
    }

    /**
     * @param non-empty-string $path
     * @param Middleware|list<Middleware> $middleware
     * @param non-empty-string|null $name
     */
    public function put(
        string $path,
        string|array|callable|MiddlewareInterface|RequestHandlerInterface $middleware,
        ?string $name = null
    ): Route
    {
        return $this->route($path, $middleware, ['PUT'], $name);
    }

    /**
     * @param non-empty-string $path
     * @param Middleware|list<Middleware> $middleware
     * @param non-empty-string|null $name
     */
    public function patch(
        string $path,
        string|array|callable|MiddlewareInterface|RequestHandlerInterface $middleware,
        ?string $name = null
    ): Route
    {
        return $this->route($path, $middleware, ['PATCH'], $name);
    }

    /**
     * @param non-empty-string $path
     * @param Middleware|list<Middleware> $middleware
     * @param non-empty-string|null $name
     */
    public function delete(
        string $path,
        string|array|callable|MiddlewareInterface|RequestHandlerInterface $middleware,
        ?string $name = null
    ): Route
    {
        return $this->route($path, $middleware, ['DELETE'], $name);
    }

    /**
     * @param non-empty-string $path
     * @param Middleware|list<Middleware> $middleware
     * @param non-empty-string|null $name
     */
    public function any(
        string $path,
        string|array|callable|MiddlewareInterface|RequestHandlerInterface $middleware,
        ?string $name = null
    ): Route
    {
        return $this->route($path, $middleware, null, $name);
    }

    /**
     * @return Route[]
     */
    public function getRoutes(): array
    {
        return $this->routes->getRoutes();
    }
}
