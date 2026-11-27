<?php declare(strict_types=1);

use Nyholm\Psr7\Factory\Psr17Factory;
use Piccolo\Container\ContainerBuilder;
use Piccolo\ServiceProvider\HttpFactoryServiceProvider;
use Psr\Http\Message\{RequestFactoryInterface,
    ResponseFactoryInterface,
    ServerRequestFactoryInterface,
    StreamFactoryInterface,
    UploadedFileFactoryInterface,
    UriFactoryInterface};

test('uses Nyholm PSR-7 by default for the :dataset factory', function (string $factory) {
    $container = new ContainerBuilder()->addProvider(new HttpFactoryServiceProvider())->build();

    expect($container->get($factory))->toBeInstanceOf(Psr17Factory::class);
})->with([
    RequestFactoryInterface::class,
    ResponseFactoryInterface::class,
    ServerRequestFactoryInterface::class,
    StreamFactoryInterface::class,
    UploadedFileFactoryInterface::class,
    UriFactoryInterface::class,
]);

test('uses the factories given to the provider instead', function () {
    $responseFactory = new class extends Psr17Factory {};

    $container = new ContainerBuilder()
        ->addProvider(new HttpFactoryServiceProvider(responseFactory: $responseFactory))
        ->build();

    expect($container->get(ResponseFactoryInterface::class))->toBe($responseFactory)
        ->and($container->get(StreamFactoryInterface::class))->toBeInstanceOf(Psr17Factory::class);
});
