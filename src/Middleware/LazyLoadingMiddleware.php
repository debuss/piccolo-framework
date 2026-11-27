<?php declare(strict_types=1);

namespace Piccolo\Middleware;

use Piccolo\Container\ScopeInterface;
use Psr\Container\{ContainerExceptionInterface, ContainerInterface, NotFoundExceptionInterface};
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use UnexpectedValueException;

/**
 * LazyLoadingMiddleware
 *
 * Resolves the middleware (or request handler) from the container only when a request reaches it, from the scope of the
 * request (see Application) when it has one: its scoped dependencies are then the ones of the request.
 */
readonly class LazyLoadingMiddleware implements MiddlewareInterface
{

    public function __construct(
        private ContainerInterface $container,
        private string $middleware
    ) {}

    /**
     * The container entry the middleware is resolved from, usually its class name.
     */
    public function getMiddleware(): string
    {
        return $this->middleware;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws UnexpectedValueException When the entry is neither a middleware nor a request handler.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $scope = $request->getAttribute(ScopeInterface::class);
        $container = $scope instanceof ContainerInterface ? $scope : $this->container;

        $middleware = $container->get($this->middleware);

        return match (true) {
            $middleware instanceof MiddlewareInterface => $middleware->process($request, $handler),
            $middleware instanceof RequestHandlerInterface => $middleware->handle($request),
            default => throw new UnexpectedValueException(sprintf(
                '"%s" must resolve to a %s or a %s, %s given',
                $this->middleware,
                MiddlewareInterface::class,
                RequestHandlerInterface::class,
                get_debug_type($middleware)
            )),
        };
    }
}
