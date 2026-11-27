<?php declare(strict_types=1);

namespace Piccolo\ServiceProvider;

use Awareness\{RequestFactoryAwareInterface,
    ResponseFactoryAwareInterface,
    ServerRequestFactoryAwareInterface,
    StreamFactoryAwareInterface,
    UploadedFileFactoryAwareInterface,
    UriFactoryAwareInterface};
use Nyholm\Psr7\Factory\Psr17Factory;
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
 * The PSR-7 / PSR-17 implementation is only chosen here: Nyholm PSR-7 by default, lighter and faster than Laminas
 * Diactoros. To use another implementation, add this provider again in config/container.php with its factories, the
 * rest of the framework only relies on the PSR-17 interfaces.
 *
 * The services implementing the *FactoryAwareInterface of debuss-a/awareness get the factories after their creation.
 *
 * @see https://github.com/Nyholm/psr7
 * @see https://github.com/debuss/awareness
 */
class HttpFactoryServiceProvider implements ServiceProvider
{

    public function __construct(
        private readonly RequestFactoryInterface $requestFactory = new Psr17Factory(),
        private readonly ResponseFactoryInterface $responseFactory = new Psr17Factory(),
        private readonly ServerRequestFactoryInterface $serverRequestFactory = new Psr17Factory(),
        private readonly StreamFactoryInterface $streamFactory = new Psr17Factory(),
        private readonly UploadedFileFactoryInterface $uploadedFileFactory = new Psr17Factory(),
        private readonly UriFactoryInterface $uriFactory = new Psr17Factory()
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
