<?php declare(strict_types=1);

namespace Piccolo\ServiceProvider;

use Awareness\ContainerAwareInterface;
use Debuss\ServerRequestFactory\ServerRequestFactory;
use Laminas\Stratigility\{MiddlewarePipe, MiddlewarePipeInterface};
use Laminas\Stratigility\Handler\NotFoundHandler;
use Laminas\Stratigility\Middleware\OriginalMessages;
use Mezzio\Helper\BodyParams\BodyParamsMiddleware;
use Piccolo\Application;
use Piccolo\Container\{Container, ContainerBuilder, ServiceProvider};
use Piccolo\Http\{ErrorLogListener, ServerRequestErrorResponseGenerator};
use Piccolo\Middleware\{ApiAcceptHeaderMiddleware, HttpCacheMiddleware, RequestIdMiddleware};
use Piccolo\Runtime\{FrankenPhpWorkerRunner, RunnerInterface, SapiRunner};
use Psr\Container\ContainerInterface;
use Psr\Http\Message\{ServerRequestFactoryInterface,
    StreamFactoryInterface,
    UploadedFileFactoryInterface,
    UriFactoryInterface};

/**
 * Application Service Provider
 *
 * The application, its middleware pipeline, and the runner of the environment: the FrankenPHP worker runner when the
 * script is run by a FrankenPHP worker, the classic SAPI runner otherwise.
 *
 * The stateless middlewares of the framework are singletons: in a worker, they are created once instead of being
 * autowired again on each request (the other middlewares and the handlers of the application stay transient).
 *
 * The services implementing ContainerAwareInterface get the container after their creation: the scope of the request
 * they are created in, or the root container (for the singletons).
 *
 * @see https://docs.laminas.dev/laminas-httphandlerrunner/runner/
 * @see https://frankenphp.dev/docs/worker/
 */
class ApplicationServiceProvider implements ServiceProvider
{

    public function register(ContainerBuilder $builder): void
    {
        $builder->singleton(Application::class);

        // A single pipeline, filled by config/pipeline.php when the application boots
        $builder->singleton(MiddlewarePipe::class, static fn () => new MiddlewarePipe());
        $builder->alias(MiddlewarePipeInterface::class, MiddlewarePipe::class);

        // Explicit factory, as TrustedProxies cannot be autowired. Behind a reverse proxy or a load balancer, register
        // it again with a TrustedProxies argument to read the forwarded headers (client IP, scheme, host, ...).
        // See https://github.com/debuss/server-request-factory#trusted-proxies
        $builder->singleton(
            ServerRequestFactory::class,
            static fn (
                ServerRequestFactoryInterface $serverRequestFactory,
                UriFactoryInterface $uriFactory,
                UploadedFileFactoryInterface $uploadedFileFactory,
                StreamFactoryInterface $streamFactory
            ) => new ServerRequestFactory($serverRequestFactory, $uriFactory, $uploadedFileFactory, $streamFactory)
        );

        $builder->singleton(
            RunnerInterface::class,
            static fn (ServerRequestFactory $serverRequestFactory, Container $container): RunnerInterface =>
                FrankenPhpWorkerRunner::isSupported()
                    ? new FrankenPhpWorkerRunner(
                        $serverRequestFactory,
                        $container,
                        max(0, (int)($_SERVER['MAX_REQUESTS'] ?? 0))
                    )
                    : new SapiRunner($serverRequestFactory, $container)
        );

        $builder->singleton(ServerRequestErrorResponseGenerator::class);
        $builder->singleton(ErrorLogListener::class);

        // Stateless middlewares: nothing of a request is kept on them
        $builder->singleton(RequestIdMiddleware::class);
        $builder->singleton(OriginalMessages::class);
        $builder->singleton(ApiAcceptHeaderMiddleware::class);
        $builder->singleton(HttpCacheMiddleware::class);
        $builder->singleton(BodyParamsMiddleware::class);
        $builder->singleton(NotFoundHandler::class);

        $builder->inflect(
            ContainerAwareInterface::class,
            static fn (ContainerAwareInterface $service, ContainerInterface $container) => $service->setContainer($container)
        );
    }
}
