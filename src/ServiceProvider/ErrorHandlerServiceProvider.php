<?php declare(strict_types=1);

namespace Piccolo\ServiceProvider;

use Laminas\Stratigility\Middleware\{ErrorHandler, ErrorResponseGenerator};
use Piccolo\Container\{ContainerBuilder, ServiceProvider};
use Piccolo\Environment;
use Piccolo\Http\ErrorLogListener;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Error Handler Service Provider
 *
 * This service provider registers the error handler middleware in the container.
 * Errors are logged with the provided logger, and the response only contains the exception details (message and
 * trace) outside of production, otherwise a generic reason phrase is returned.
 *
 * @see https://docs.laminas.dev/laminas-stratigility/v4/error-handlers/
 */
class ErrorHandlerServiceProvider implements ServiceProvider
{

    public function register(ContainerBuilder $builder): void
    {
        $builder->singleton(
            ErrorHandler::class,
            static function (
                ResponseFactoryInterface $responseFactory,
                Environment $environment,
                ErrorLogListener $listener
            ): ErrorHandler {
                $handler = new ErrorHandler(
                    $responseFactory,
                    new ErrorResponseGenerator(isDevelopmentMode: !$environment->isProduction())
                );

                $handler->attachListener($listener);

                return $handler;
            }
        );
    }
}
