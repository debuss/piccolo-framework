<?php declare(strict_types=1);

use Piccolo\Middleware\HttpCacheMiddleware;
use Nyholm\Psr7\{Response, ServerRequest};
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The stream factory is set manually, as the container does after resolution through the Awareness pattern.
 */
function makeHttpCacheMiddleware(int $maxAge = 3600, bool $private = false): HttpCacheMiddleware
{
    $middleware = new HttpCacheMiddleware($maxAge, $private);
    $middleware->setStreamFactory(new Psr17Factory());

    return $middleware;
}

/**
 * A request handler returning a response with the given status and body.
 */
function respondWith(string $body, int $status = 200, array $headers = []): RequestHandlerInterface
{
    return new class ($body, $status, $headers) implements RequestHandlerInterface {
        public function __construct(private string $body, private int $status, private array $headers) {}

        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            $response = new Response($this->status, $this->headers);
            $response->getBody()->write($this->body);

            return $response->withHeader('Content-Length', (string)strlen($this->body));
        }
    };
}

function etagOf(string $body): string
{
    return sprintf('"%s"', hash('xxh128', $body));
}

test('adds the cache headers to a successful GET response, and keeps its body', function () {
    $response = makeHttpCacheMiddleware()->process(new ServerRequest('GET', '/'), respondWith('openapi: 3.1.0'));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getHeaderLine('Cache-Control'))->toBe('public, max-age=3600')
        ->and($response->getHeaderLine('ETag'))->toBe(etagOf('openapi: 3.1.0'))
        ->and((string)$response->getBody())->toBe('openapi: 3.1.0');
});

test('answers 304 without body when If-None-Match matches the ETag', function (string $ifNoneMatch) {
    $request = new ServerRequest('GET', '/', ['If-None-Match' => $ifNoneMatch]);

    $response = makeHttpCacheMiddleware()->process($request, respondWith('openapi: 3.1.0'));

    expect($response->getStatusCode())->toBe(304)
        ->and($response->getHeaderLine('ETag'))->toBe(etagOf('openapi: 3.1.0'))
        ->and($response->getHeaderLine('Cache-Control'))->toBe('public, max-age=3600')
        ->and($response->hasHeader('Content-Length'))->toBeFalse()
        ->and((string)$response->getBody())->toBe('');
})->with([
    'the same ETag' => [etagOf('openapi: 3.1.0')],
    'a weak ETag' => ['W/' . etagOf('openapi: 3.1.0')],
    'one ETag of a list' => ['"outdated", ' . etagOf('openapi: 3.1.0')],
    'any ETag' => ['*'],
]);

test('answers 200 with the body when If-None-Match does not match the ETag', function () {
    $request = new ServerRequest('GET', '/', ['If-None-Match' => etagOf('openapi: 3.0.0')]);

    $response = makeHttpCacheMiddleware()->process($request, respondWith('openapi: 3.1.0'));

    expect($response->getStatusCode())->toBe(200)
        ->and((string)$response->getBody())->toBe('openapi: 3.1.0');
});

test('leaves the responses of other HTTP methods untouched', function () {
    $response = makeHttpCacheMiddleware()->process(new ServerRequest('POST', '/'), respondWith('created', 201));

    expect($response->getStatusCode())->toBe(201)
        ->and($response->hasHeader('ETag'))->toBeFalse()
        ->and($response->hasHeader('Cache-Control'))->toBeFalse();
});

test('leaves unsuccessful responses untouched', function () {
    $response = makeHttpCacheMiddleware()->process(new ServerRequest('GET', '/'), respondWith('Not Found', 404));

    expect($response->getStatusCode())->toBe(404)
        ->and($response->hasHeader('ETag'))->toBeFalse()
        ->and($response->hasHeader('Cache-Control'))->toBeFalse();
});

test('uses the max age and the visibility it is created with', function (int $maxAge, bool $private, string $cacheControl) {
    $response = makeHttpCacheMiddleware($maxAge, $private)->process(new ServerRequest('GET', '/'), respondWith('content'));

    expect($response->getHeaderLine('Cache-Control'))->toBe($cacheControl)
        ->and($response->getHeaderLine('ETag'))->toBe(etagOf('content'));
})->with([
    'public' => [600, false, 'public, max-age=600'],
    'private' => [60, true, 'private, max-age=60'],
    'revalidated on each request' => [0, true, 'private, no-cache'],
]);

test('keeps the Cache-Control header of the handler', function () {
    $response = makeHttpCacheMiddleware()->process(
        new ServerRequest('GET', '/'),
        respondWith('content', headers: ['Cache-Control' => 'private, max-age=10'])
    );

    expect($response->getHeaderLine('Cache-Control'))->toBe('private, max-age=10')
        ->and($response->getHeaderLine('ETag'))->toBe(etagOf('content'));
});

test('leaves a response that must not be stored untouched', function () {
    $response = makeHttpCacheMiddleware()->process(
        new ServerRequest('GET', '/', ['If-None-Match' => '*']),
        respondWith('content', headers: ['Cache-Control' => 'No-Store'])
    );

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getHeaderLine('Cache-Control'))->toBe('No-Store')
        ->and($response->hasHeader('ETag'))->toBeFalse();
});

test('uses the ETag of the handler, and compares it weakly', function () {
    $handler = respondWith('content', headers: ['ETag' => 'W/"v42"']);

    $fresh = makeHttpCacheMiddleware()->process(new ServerRequest('GET', '/'), $handler);
    $cached = makeHttpCacheMiddleware()->process(new ServerRequest('GET', '/', ['If-None-Match' => '"v42"']), $handler);

    expect($fresh->getHeaderLine('ETag'))->toBe('W/"v42"')
        ->and($cached->getStatusCode())->toBe(304);
});
