<?php declare(strict_types=1);

namespace Piccolo\ServiceProvider;

use Mezzio\Router\{FastRouteRouter, RouteCollector, RouteCollectorInterface, RouterInterface};
use Mezzio\Router\Middleware\{DispatchMiddleware,
    ImplicitHeadMiddleware,
    ImplicitOptionsMiddleware,
    MethodNotAllowedMiddleware,
    RouteMiddleware};
use Piccolo\Container\{ContainerBuilder, ServiceProvider};
use Piccolo\Environment;

/**
 * FastRoute Router Service Provider
 *
 * The router, the route collector filled by config/routes.php, and the routing middlewares, all stateless singletons.
 *
 * @see https://docs.mezzio.dev/mezzio/v3/features/router/fast-route/
 */
class FastRouteRouterServiceProvider implements ServiceProvider
{

    public function register(ContainerBuilder $builder): void
    {
        $builder->singleton(
            FastRouteRouter::class,
            static fn (Environment $environment): FastRouteRouter => new FastRouteRouter(config: [
                FastRouteRouter::CONFIG_CACHE_ENABLED => $environment->isProduction(),
                FastRouteRouter::CONFIG_CACHE_FILE => cache_path('routes.cache.php')
            ])
        );
        $builder->alias(RouterInterface::class, FastRouteRouter::class);

        $builder->singleton(RouteCollector::class);
        $builder->alias(RouteCollectorInterface::class, RouteCollector::class);

        $builder->singleton(RouteMiddleware::class);
        $builder->singleton(ImplicitHeadMiddleware::class);
        $builder->singleton(ImplicitOptionsMiddleware::class);
        $builder->singleton(MethodNotAllowedMiddleware::class);
        $builder->singleton(DispatchMiddleware::class);
    }
}
