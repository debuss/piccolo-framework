<?php declare(strict_types=1);

namespace Tests\Fixtures;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Answers with what it received, to check the requests created by the TestClient.
 */
final class EchoHandler implements RequestHandlerInterface
{

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return JsonResponse::of([
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'query' => $request->getQueryParams(),
            'header' => $request->getHeaderLine('X-Test'),
            'body' => (string)$request->getBody(),
            'parsed' => $request->getParsedBody(),
        ])->withHeader('X-Echo', 'yes');
    }
}
