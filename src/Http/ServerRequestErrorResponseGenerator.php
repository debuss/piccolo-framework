<?php declare(strict_types=1);

namespace Piccolo\Http;

use Piccolo\Environment;
use Piccolo\Middleware\RequestIdMiddleware;
use Debuss\ServerRequestFactory\BadRequestException;
use Psr\Http\Message\{ResponseFactoryInterface, ResponseInterface};
use Psr\Log\{LoggerInterface, LogLevel};
use Throwable;

/**
 * Server Request Error Response Generator
 *
 * Generates the response when the server request cannot be created from the globals (invalid header, malformed
 * uploaded files, ...). The request never reaches the middleware pipeline in this case, so neither the ErrorHandler
 * nor its logger are involved: the error is logged here.
 *
 * The response has an X-Request-Id header and the log record its identifier, as for the other requests (see
 * RequestIdMiddleware): the one sent by the reverse proxy when valid, a random one otherwise.
 *
 * A malformed client request (BadRequestException) gives a 400 and is logged as a warning, as there is nothing to fix
 * on our side. Any other error (invalid configuration, unreadable uploaded file, ...) comes from the server: it gives a
 * 500 and is logged as an error. The exception is only displayed outside of production.
 *
 * @see https://docs.laminas.dev/laminas-httphandlerrunner/runner/
 */
readonly class ServerRequestErrorResponseGenerator
{

    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private LoggerInterface $logger,
        private Environment $environment,
        private RequestIdProcessor $requestIdProcessor
    ) {}

    public function __invoke(Throwable $e): ResponseInterface
    {
        $isBadRequest = $e instanceof BadRequestException;

        // The request could not be created: its header is read from the globals
        $header = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
        $requestId = RequestIdMiddleware::identify(is_string($header) ? $header : '');

        $this->requestIdProcessor->setRequestId($requestId);
        try {
            $this->logger->log($isBadRequest ? LogLevel::WARNING : LogLevel::ERROR, $e->getMessage(), ['exception' => $e]);
        } finally {
            $this->requestIdProcessor->setRequestId(null);
        }

        $response = $this->responseFactory->createResponse($isBadRequest ? 400 : 500);
        $response->getBody()->write(
            $this->environment->isProduction() ? $response->getReasonPhrase() : (string)$e
        );

        return $response
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withHeader(RequestIdMiddleware::HEADER, $requestId);
    }
}
