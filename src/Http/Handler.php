<?php declare(strict_types=1);

namespace Piccolo\Http;

use InvalidArgumentException;
use JsonException;
use JsonSerializable;
use LogicException;
use Psr\Http\Message\{ResponseFactoryInterface, ResponseInterface, UriInterface};
use Psr\Http\Server\RequestHandlerInterface;
use Awareness\{ResponseFactoryAwareInterface,
    ResponseFactoryAwareTrait,
    StreamFactoryAwareInterface,
    StreamFactoryAwareTrait};

/**
 * Base class for the request handlers, building responses with the PSR-17 factories (injected after resolution by
 * the container, see HttpFactoryServiceProvider), so that handlers do not depend on a PSR-7 implementation.
 */
abstract class Handler implements RequestHandlerInterface, ResponseFactoryAwareInterface, StreamFactoryAwareInterface
{

    use ResponseFactoryAwareTrait, StreamFactoryAwareTrait;

    protected function html(string $content, int $status = 200): ResponseInterface
    {
        return $this->content($content, 'text/html; charset=utf-8', $status);
    }

    protected function text(string $content, int $status = 200): ResponseInterface
    {
        return $this->content($content, 'text/plain; charset=utf-8', $status);
    }

    protected function xml(string $content, int $status = 200): ResponseInterface
    {
        return $this->content($content, 'application/xml; charset=utf-8', $status);
    }

    /**
     * @param array<mixed>|JsonSerializable $data
     * @param int<1, max> $depth
     *
     * @throws JsonException When the data cannot be encoded
     */
    protected function json(
        array|JsonSerializable $data,
        int $status = 200,
        int $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        int $depth = 512
    ): ResponseInterface {
        return $this->content(json_encode($data, $flags | JSON_THROW_ON_ERROR, $depth), 'application/json', $status);
    }

    /**
     * A redirection to the URI: 302 Found by default, 303 See Other after a form, 301 / 308 for a permanent one (307 /
     * 308 keep the method and the body of the request).
     *
     * @throws InvalidArgumentException When the status is not a redirection (3xx)
     */
    protected function redirect(string|UriInterface $uri, int $status = 302): ResponseInterface
    {
        if ($status < 300 || $status > 399) {
            throw new InvalidArgumentException(sprintf('A redirection status must be 3xx, %d given', $status));
        }

        return $this->responseFactory()->createResponse($status)->withHeader('Location', (string)$uri);
    }

    /**
     * A response without body: 204 No Content by default.
     */
    protected function empty(int $status = 204): ResponseInterface
    {
        return $this->responseFactory()->createResponse($status);
    }

    private function content(string $content, string $contentType, int $status): ResponseInterface
    {
        $response = $this->responseFactory()->createResponse($status);
        $response->getBody()->write($content);

        return $response->withHeader('Content-Type', $contentType);
    }

    /**
     * @throws LogicException When the factory is not set: the handler is not resolved from the container.
     */
    private function responseFactory(): ResponseFactoryInterface
    {
        return $this->responseFactory ?? throw new LogicException(sprintf(
            'The response factory of %s is not set: resolve it from the container, or call setResponseFactory()',
            static::class
        ));
    }
}
