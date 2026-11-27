<?php declare(strict_types=1);

use Nyholm\Psr7\{Response, ServerRequest};
use Piccolo\Environment;
use Piccolo\Middleware\SecurityHeadersMiddleware;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A request handler returning a response with the given headers.
 */
function respondWithHeaders(array $headers = []): RequestHandlerInterface
{
    return new readonly class ($headers) implements RequestHandlerInterface {
        public function __construct(private array $headers) {}

        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return new Response(200, $this->headers);
        }
    };
}

test('adds the security headers, without HSTS outside production', function () {
    $response = new SecurityHeadersMiddleware(Environment::Development)
        ->process(new ServerRequest('GET', '/'), respondWithHeaders());

    expect($response->getHeaderLine('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->getHeaderLine('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($response->getHeaderLine('X-Frame-Options'))->toBe('DENY')
        ->and($response->hasHeader('Strict-Transport-Security'))->toBeFalse();
});

test('adds HSTS in production', function () {
    $response = new SecurityHeadersMiddleware(Environment::Production)
        ->process(new ServerRequest('GET', '/'), respondWithHeaders());

    expect($response->getHeaderLine('Strict-Transport-Security'))->toBe('max-age=31536000; includeSubDomains');
});

test('keeps the headers set by the handler', function () {
    $response = new SecurityHeadersMiddleware(Environment::Production)
        ->process(new ServerRequest('GET', '/'), respondWithHeaders(['x-frame-options' => 'SAMEORIGIN']));

    expect($response->getHeaderLine('X-Frame-Options'))->toBe('SAMEORIGIN');
});

test('replaces, removes or adds headers given to it', function () {
    $middleware = new SecurityHeadersMiddleware(Environment::Production, [
        'X-Frame-Options' => 'SAMEORIGIN',
        'Strict-Transport-Security' => null,
        'Cross-Origin-Opener-Policy' => 'same-origin',
    ]);

    $response = $middleware->process(new ServerRequest('GET', '/'), respondWithHeaders());

    expect($response->getHeaderLine('X-Frame-Options'))->toBe('SAMEORIGIN')
        ->and($response->hasHeader('Strict-Transport-Security'))->toBeFalse()
        ->and($response->getHeaderLine('Cross-Origin-Opener-Policy'))->toBe('same-origin')
        ->and($response->getHeaderLine('X-Content-Type-Options'))->toBe('nosniff');
});
