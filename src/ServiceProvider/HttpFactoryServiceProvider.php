<?php declare(strict_types=1);

namespace Piccolo\ServiceProvider;

use Awareness\{RequestFactoryAwareInterface,
    ResponseFactoryAwareInterface,
    ServerRequestFactoryAwareInterface,
    StreamFactoryAwareInterface,
    UploadedFileFactoryAwareInterface,
    UriFactoryAwareInterface};
use Laminas\Diactoros\{RequestFactory,
    ResponseFactory,
    ServerRequestFactory,
    StreamFactory,
    UploadedFileFactory,
    UriFactory};
use Piccolo\Container\{ContainerBuilder, ServiceProvider};
use Psr\Container\ContainerInterface;
use Psr\Http\Message\{RequestFactoryInterface,
    ResponseFactoryInterface,
    ServerRequestFactoryInterface,
    StreamFactoryInterface,
    UploadedFileFactoryInterface,
    UriFactoryInterface};

/**
 * PSR-17 HTTP Factories Service Provider
 *
 * The PSR-7 / PSR-17 implementation is only chosen here: Laminas Diactoros by default. To use another implementation,
 * add this provider again in config/container.php with its factories, the rest of the framework only relies on the
 * PSR-17 interfaces.
 *
 * The services implementing the *FactoryAwareInterface of debuss-a/awareness get the factories after their creation.
 *
 * @see https://docs.laminas.dev/laminas-diactoros/v3/factories/
 * @see https://github.com/debuss/awareness
 */
class HttpFactoryServiceProvider implements ServiceProvider
{

    public function __construct(
        private readonly RequestFactoryInterface $requestFactory = new RequestFactory(),
        private readonly ResponseFactoryInterface $responseFactory = new ResponseFactory(),
        private readonly ServerRequestFactoryInterface $serverRequestFactory = new ServerRequestFactory(),
        private readonly StreamFactoryInterface $streamFactory = new StreamFactory(),
        private readonly UploadedFileFactoryInterface $uploadedFileFactory = new UploadedFileFactory(),
        private readonly UriFactoryInterface $uriFactory = new UriFactory()
    ) {}

    public function register(ContainerBuilder $builder): void
    {
        $builder->instance(RequestFactoryInterface::class, $this->requestFactory);
        $builder->instance(ResponseFactoryInterface::class, $this->responseFactory);
        $builder->instance(ServerRequestFactoryInterface::class, $this->serverRequestFactory);
        $builder->instance(StreamFactoryInterface::class, $this->streamFactory);
        $builder->instance(UploadedFileFactoryInterface::class, $this->uploadedFileFactory);
        $builder->instance(UriFactoryInterface::class, $this->uriFactory);

        $builder->inflect(
            RequestFactoryAwareInterface::class,
            static fn (RequestFactoryAwareInterface $service, ContainerInterface $container) =>
                $service->setRequestFactory($container->get(RequestFactoryInterface::class))
        );

        $builder->inflect(
            ResponseFactoryAwareInterface::class,
            static fn (ResponseFactoryAwareInterface $service, ContainerInterface $container) =>
                $service->setResponseFactory($container->get(ResponseFactoryInterface::class))
        );

        $builder->inflect(
            ServerRequestFactoryAwareInterface::class,
            static fn (ServerRequestFactoryAwareInterface $service, ContainerInterface $container) =>
                $service->setServerRequestFactory($container->get(ServerRequestFactoryInterface::class))
        );

        $builder->inflect(
            StreamFactoryAwareInterface::class,
            static fn (StreamFactoryAwareInterface $service, ContainerInterface $container) =>
                $service->setStreamFactory($container->get(StreamFactoryInterface::class))
        );

        $builder->inflect(
            UploadedFileFactoryAwareInterface::class,
            static fn (UploadedFileFactoryAwareInterface $service, ContainerInterface $container) =>
                $service->setUploadedFileFactory($container->get(UploadedFileFactoryInterface::class))
        );

        $builder->inflect(
            UriFactoryAwareInterface::class,
            static fn (UriFactoryAwareInterface $service, ContainerInterface $container) =>
                $service->setUriFactory($container->get(UriFactoryInterface::class))
        );
    }
}
