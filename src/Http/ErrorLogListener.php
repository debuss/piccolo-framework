<?php declare(strict_types=1);

namespace Piccolo\Http;

use Psr\Http\Message\{RequestInterface, ResponseInterface, ServerRequestInterface, UriInterface};
use Psr\Log\{LoggerInterface, LogLevel};
use Throwable;

/**
 * Error Log Listener
 *
 * Logs the errors caught by the ErrorHandler and ProblemDetailsMiddleware listeners (both share this signature): as
 * errors for a 5xx response, as warnings for a 4xx one (an error of the client, e.g. a NotFoundException).
 *
 * Only non-sensitive request data is logged: headers, cookies and body can contain credentials (Authorization header,
 * session cookie, password field, ...) and logs are often shipped to third-party services.
 * Route attributes are not available here: both middlewares are piped before the routing, and PSR-7 requests are
 * immutable, so they only see the request as it entered them.
 * The URI comes from the `originalUri` attribute (OriginalMessages middleware) when available, as middleware piped on
 * a path (e.g. `/api`) see the URI without that path prefix.
 */
readonly class ErrorLogListener
{

    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function __invoke(Throwable $error, RequestInterface $request, ResponseInterface $response): void
    {
        $level = $response->getStatusCode() >= 500 ? LogLevel::ERROR : LogLevel::WARNING;
        $uri = $request instanceof ServerRequestInterface ? $request->getAttribute('originalUri') : null;

        $this->logger->log($level, $error->getMessage(), [
            'exception' => $error,
            'request' => [
                'method' => $request->getMethod(),
                'uri' => (string)($uri instanceof UriInterface ? $uri : $request->getUri()),
            ],
            'response' => [
                'status' => $response->getStatusCode(),
            ],
        ]);
    }
}
