<?php declare(strict_types=1);

use Nyholm\Psr7\ServerRequest;
use Piccolo\Middleware\LazyLoadingMiddleware;
use Tests\Fixtures\{FakeContainer, NoopMiddleware, NoopRequestHandler};

test('resolves the middleware or the request handler when a request reaches it', function (object $entry) {
    $container = new FakeContainer();
    $container->set('entry', $entry);

    $response = new LazyLoadingMiddleware($container, 'entry')
        ->process(new ServerRequest('GET', '/'), new NoopRequestHandler());

    expect($response->getStatusCode())->toBe(200);
})->with([
    'middleware' => [new NoopMiddleware()],
    'request handler' => [new NoopRequestHandler()],
]);

test('fails when the entry is neither a middleware nor a request handler', function () {
    $container = new FakeContainer();
    $container->set('entry', new stdClass());

    expect(fn () => new LazyLoadingMiddleware($container, 'entry')->process(new ServerRequest('GET', '/'), new NoopRequestHandler()))
        ->toThrow(UnexpectedValueException::class, '"entry" must resolve to a Psr\Http\Server\MiddlewareInterface or a Psr\Http\Server\RequestHandlerInterface, stdClass given');
});
