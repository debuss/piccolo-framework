<?php declare(strict_types=1);

namespace Piccolo\Console;

use Laminas\Stratigility\Middleware\PathMiddlewareDecorator;
use Laminas\Stratigility\MiddlewarePipe;
use Piccolo\Middleware\LazyLoadingMiddleware;
use LogicException;
use Psr\Http\Server\MiddlewareInterface;
use ReflectionProperty;

/**
 * Looks into the middlewares of the pipeline and of the routes: the nested pipes (a route with middlewares, or a group
 * of middlewares piped on a path), the middlewares piped on a path, and the middlewares and handlers resolved from the
 * container when a request reaches them.
 *
 * @internal Used by the console commands.
 */
final class MiddlewareInspector
{

    /**
     * The middlewares, followed by the handler: "Middleware → Handler".
     */
    public static function describe(MiddlewareInterface $middleware): string
    {
        return match (true) {
            $middleware instanceof MiddlewarePipe => implode(
                ' → ',
                array_map(self::describe(...), iterator_to_array($middleware, false))
            ),
            $middleware instanceof LazyLoadingMiddleware => $middleware->getMiddleware(),
            default => $middleware::class,
        };
    }

    /**
     * The container entries of the middlewares and handlers resolved when a request reaches them, in order.
     *
     * @return list<string>
     */
    public static function services(MiddlewareInterface $middleware): array
    {
        return match (true) {
            $middleware instanceof MiddlewarePipe => array_merge(
                ...array_map(self::services(...), iterator_to_array($middleware, false))
            ),
            $middleware instanceof PathMiddlewareDecorator => self::services(self::decorated($middleware)),
            $middleware instanceof LazyLoadingMiddleware => [$middleware->getMiddleware()],
            default => [],
        };
    }

    /**
     * The middleware piped on a path: Stratigility does not expose it.
     */
    private static function decorated(PathMiddlewareDecorator $decorator): MiddlewareInterface
    {
        $middleware = new ReflectionProperty(PathMiddlewareDecorator::class, 'middleware')->getValue($decorator);

        return $middleware instanceof MiddlewareInterface
            ? $middleware
            : throw new LogicException('The middleware of a PathMiddlewareDecorator cannot be read');
    }
}
