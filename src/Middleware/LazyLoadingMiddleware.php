<?php declare(strict_types=1);

namespace Piccolo\Middleware;

use Laminas\Stratigility\Middleware\RequestHandlerMiddleware;
use Piccolo\Container\ScopeInterface;
use Psr\Container\{ContainerExceptionInterface, ContainerInterface, NotFoundExceptionInterface};
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};

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
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $container = $request->getAttribute(ScopeInterface::class) ?? $this->container;

        $middleware = $container->get($this->middleware);
        if ($middleware instanceof RequestHandlerInterface && ! $middleware instanceof MiddlewareInterface) {
            $middleware = new RequestHandlerMiddleware($middleware);
        }

        return $middleware->process($request, $handler);
    }
}
