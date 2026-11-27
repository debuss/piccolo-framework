<?php declare(strict_types=1);

use Piccolo\Http\Handler;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface, UriInterface};

/**
 * A concrete handler exposing the protected helpers. The factories are set manually, as the container does after
 * resolution through the Awareness pattern.
 */
function makeHandler(): Handler
{
    $handler = new class extends Handler {
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return $this->html('');
        }

        public function callHtml(string $content, int $status = 200): ResponseInterface
        {
            return $this->html($content, $status);
        }

        public function callJson(array|JsonSerializable $data, int $status = 200): ResponseInterface
        {
            return $this->json($data, $status);
        }

        public function callText(string $content, int $status = 200): ResponseInterface
        {
            return $this->text($content, $status);
        }

        public function callXml(string $content, int $status = 200): ResponseInterface
        {
            return $this->xml($content, $status);
        }

        public function callRedirect(string|UriInterface $uri, int $status = 302): ResponseInterface
        {
            return $this->redirect($uri, $status);
        }

        public function callEmpty(int $status = 204): ResponseInterface
        {
            return $this->empty($status);
        }
    };

    $handler->setResponseFactory(new Psr17Factory());
    $handler->setStreamFactory(new Psr17Factory());

    return $handler;
}

test('is a request handler', function () {
    expect(makeHandler()->handle(new ServerRequest('GET', '/')))->toBeInstanceOf(ResponseInterface::class);
});

test('creates an HTML response', function () {
    $response = makeHandler()->callHtml('<h1>Hello</h1>', 201);

    expect($response->getStatusCode())->toBe(201)
        ->and($response->getHeaderLine('Content-Type'))->toBe('text/html; charset=utf-8')
        ->and((string)$response->getBody())->toBe('<h1>Hello</h1>');
});

test('creates a JSON response', function () {
    $response = makeHandler()->callJson(['id' => 1, 'url' => 'https://example.com/a'], 202);

    expect($response->getStatusCode())->toBe(202)
        ->and($response->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and((string)$response->getBody())->toBe('{"id":1,"url":"https://example.com/a"}');
});

test('encodes JsonSerializable objects', function () {
    $data = new class implements JsonSerializable {
        public function jsonSerialize(): array
        {
            return ['serialized' => true];
        }
    };

    expect((string)makeHandler()->callJson($data)->getBody())->toBe('{"serialized":true}');
});

test('keeps unicode and HTML characters as is', function () {
    $body = (string)makeHandler()->callJson(['title' => "Café <b>&</b> l'été"])->getBody();

    expect($body)->toBe('{"title":"Café <b>&</b> l\'été"}');
});

test('throws a JsonException when the data cannot be encoded', function () {
    makeHandler()->callJson(['invalid' => "\xB1\x31"]);
})->throws(JsonException::class);

test('creates a plain text response', function () {
    $response = makeHandler()->callText('pong', 201);

    expect($response->getStatusCode())->toBe(201)
        ->and($response->getHeaderLine('Content-Type'))->toBe('text/plain; charset=utf-8')
        ->and((string)$response->getBody())->toBe('pong');
});

test('creates an XML response', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?><post id="1"/>';
    $response = makeHandler()->callXml($xml, 202);

    expect($response->getStatusCode())->toBe(202)
        ->and($response->getHeaderLine('Content-Type'))->toBe('application/xml; charset=utf-8')
        ->and((string)$response->getBody())->toBe($xml);
});

test('redirects with a 302 Found by default', function () {
    $response = makeHandler()->callRedirect('/login');

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getHeaderLine('Location'))->toBe('/login')
        ->and((string)$response->getBody())->toBe('');
});

test('redirects to a URI with the given 3xx status', function (int $status) {
    $response = makeHandler()->callRedirect(new Psr17Factory()->createUri('https://example.com/posts?page=2'), $status);

    expect($response->getStatusCode())->toBe($status)
        ->and($response->getHeaderLine('Location'))->toBe('https://example.com/posts?page=2');
})->with([301, 303, 307, 308]);

test('refuses a redirection status that is not 3xx', function (int $status) {
    makeHandler()->callRedirect('/login', $status);
})->with([200, 299, 400])->throws(InvalidArgumentException::class, 'A redirection status must be 3xx');

test('creates an empty response, 204 No Content by default', function () {
    $noContent = makeHandler()->callEmpty();
    $accepted = makeHandler()->callEmpty(202);

    expect($noContent->getStatusCode())->toBe(204)
        ->and($noContent->hasHeader('Content-Type'))->toBeFalse()
        ->and((string)$noContent->getBody())->toBe('')
        ->and($accepted->getStatusCode())->toBe(202);
});

test('tells how to set the response factory when it is missing', function () {
    $handler = new class extends Handler {
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return $this->text('');
        }
    };

    expect(fn () => $handler->handle(new ServerRequest('GET', '/')))
        ->toThrow(LogicException::class, 'resolve it from the container, or call setResponseFactory()');
});
