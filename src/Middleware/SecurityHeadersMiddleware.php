<?php declare(strict_types=1);

namespace Piccolo\Middleware;

use Piccolo\Environment;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};

/**
 * SecurityHeadersMiddleware
 *
 * Adds security headers to every response, unless the response already has them (set by a handler):
 * - `X-Content-Type-Options: nosniff`: browsers do not guess the type of a response (e.g. a text rendered as HTML);
 * - `Referrer-Policy: strict-origin-when-cross-origin`: the other sites only get the origin of a link, not its path;
 * - `X-Frame-Options: DENY`: the pages cannot be displayed in a frame of another site (clickjacking);
 * - `Strict-Transport-Security: max-age=31536000; includeSubDomains`, in production only: browsers only use HTTPS for
 *   the domain and its subdomains for a year. Never outside production, where it would stick to localhost.
 *
 * Piped right after the RequestIdMiddleware, so that the error responses have them too. A header can be replaced, or
 * removed with null, in config/container.php:
 *
 *  $builder->singleton(SecurityHeadersMiddleware::class, static fn (Environment $environment) =>
 *      new SecurityHeadersMiddleware($environment, ['X-Frame-Options' => 'SAMEORIGIN'])
 *  );
 *
 * The Content-Security-Policy depends on the pages of the application (scripts, styles, ...): it is not set here.
 */
readonly class SecurityHeadersMiddleware implements MiddlewareInterface
{

    public const array HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'X-Frame-Options' => 'DENY',
    ];

    public const array PRODUCTION_HEADERS = [
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
    ];

    /** @var array<string, string> */
    private array $headers;

    /**
     * @param array<string, string|null> $headers Headers replacing the default ones (same name), or added; null removes
     *                                            a default header.
     */
    public function __construct(Environment $environment, array $headers = [])
    {
        $this->headers = array_filter(
            [...self::HEADERS, ...($environment->isProduction() ? self::PRODUCTION_HEADERS : []), ...$headers],
            static fn (?string $value): bool => $value !== null
        );
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        foreach ($this->headers as $name => $value) {
            if (!$response->hasHeader($name)) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }
}
