<?php declare(strict_types=1);

namespace Piccolo\Middleware;

use Awareness\{StreamFactoryAwareInterface, StreamFactoryAwareTrait};
use LogicException;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface, StreamFactoryInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};

/**
 * HttpCacheMiddleware
 *
 * Lets clients cache the successful GET responses of a route: adds a `Cache-Control` header and an `ETag` computed from
 * the body, and answers `304 Not Modified` without body when the `If-None-Match` header of the request matches it, so
 * that the content is not downloaded again.
 *
 * By default, any cache can keep the response for an hour (`public, max-age=3600`). For the whole application, register
 * it with other settings in config/container.php:
 *
 *  $builder->singleton(HttpCacheMiddleware::class, static fn () => new HttpCacheMiddleware(maxAge: 600, private: true));
 *
 * `private` keeps the responses in the cache of the browser only (content of a user), and a max age of 0 gives
 * `no-cache`: the client revalidates the response on each request, downloading it only when its ETag changed.
 *
 * For a single response, the handler sets the headers itself: its `Cache-Control` is kept, and with `no-store` the
 * response is left untouched (no ETag); its `ETag` (e.g. from a version) is used instead of a hash of the body.
 *
 * Meant for the routes serving content that rarely changes, added on the handler with the `#[Middleware]` attribute:
 *
 *  #[Get('/openapi', name: 'api.v1.openapi')]
 *  #[Middleware(HttpCacheMiddleware::class)]
 *  public function handle(ServerRequestInterface $request): ResponseInterface
 *
 * The stream factory, used to empty the body of `304` responses, is injected after resolution by the container.
 */
class HttpCacheMiddleware implements MiddlewareInterface, StreamFactoryAwareInterface
{

    use StreamFactoryAwareTrait;

    private string $cacheControl;

    /**
     * @param int<0, max> $maxAge How long the response can be cached, in seconds: 0 to revalidate it on each request.
     * @param bool $private Only the browser can cache the response, not the shared caches (proxies, CDN).
     */
    public function __construct(int $maxAge = 3600, bool $private = false)
    {
        $this->cacheControl = sprintf(
            '%s, %s',
            $private ? 'private' : 'public',
            $maxAge > 0 ? sprintf('max-age=%d', $maxAge) : 'no-cache'
        );
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (!in_array($request->getMethod(), ['GET', 'HEAD'], true)
            || $response->getStatusCode() !== 200
            || str_contains(strtolower($response->getHeaderLine('Cache-Control')), 'no-store')) {
            return $response;
        }

        $etag = $response->getHeaderLine('ETag') ?: sprintf('"%s"', hash('xxh128', (string)$response->getBody()));

        $response = $response->withHeader('ETag', $etag);
        if (!$response->hasHeader('Cache-Control')) {
            $response = $response->withHeader('Cache-Control', $this->cacheControl);
        }

        if (!$this->matches($request->getHeaderLine('If-None-Match'), $etag)) {
            return $response;
        }

        return $response
            ->withStatus(304)
            ->withoutHeader('Content-Length')
            ->withBody($this->streamFactory()->createStream());
    }

    /**
     * `If-None-Match` holds `*` or a list of entity tags, compared weakly: `W/"abc"` matches `"abc"` (RFC 9110, 13.1.2).
     */
    private function matches(string $ifNoneMatch, string $etag): bool
    {
        if (trim($ifNoneMatch) === '*') {
            return true;
        }

        $etag = self::opaque($etag);
        foreach (explode(',', $ifNoneMatch) as $candidate) {
            if (self::opaque($candidate) === $etag) {
                return true;
            }
        }

        return false;
    }

    /**
     * The entity tag without its weakness indicator: `W/"abc"` is `"abc"`.
     */
    private static function opaque(string $etag): string
    {
        $etag = trim($etag);

        return str_starts_with($etag, 'W/') ? substr($etag, 2) : $etag;
    }

    /**
     * @throws LogicException When the factory is not set: the middleware is not resolved from the container.
     */
    private function streamFactory(): StreamFactoryInterface
    {
        return $this->streamFactory ?? throw new LogicException(sprintf(
            'The stream factory of %s is not set: resolve it from the container, or call setStreamFactory()',
            static::class
        ));
    }
}
