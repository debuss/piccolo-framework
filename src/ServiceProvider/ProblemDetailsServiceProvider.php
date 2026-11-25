<?php declare(strict_types=1);

namespace Piccolo\ServiceProvider;

use Mezzio\ProblemDetails\{ProblemDetailsMiddleware, ProblemDetailsResponseFactory};
use Piccolo\Container\{ContainerBuilder, ServiceProvider};
use Piccolo\Environment;
use Piccolo\Http\ErrorLogListener;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Problem Details Service Provider
 *
 * @see https://docs.mezzio.dev/mezzio-problem-details/intro/
 */
class ProblemDetailsServiceProvider implements ServiceProvider
{

    public function register(ContainerBuilder $builder): void
    {
        // Exceptions that do not implement ProblemDetailsExceptionInterface become a 500 response, their message and
        // trace are only exposed in debug mode (outside of production).
        $builder->singleton(
            ProblemDetailsResponseFactory::class,
            static fn (ResponseFactoryInterface $responseFactory, Environment $environment) =>
                new ProblemDetailsResponseFactory($responseFactory, isDebug: !$environment->isProduction())
        );

        $builder->singleton(
            ProblemDetailsMiddleware::class,
            static function (ProblemDetailsResponseFactory $factory, ErrorLogListener $listener): ProblemDetailsMiddleware {
                $middleware = new ProblemDetailsMiddleware($factory);
                $middleware->attachListener($listener);

                return $middleware;
            }
        );
    }
}
