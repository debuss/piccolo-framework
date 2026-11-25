<?php declare(strict_types=1);

namespace Tests\Fixtures;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * A request handler using the scoped RequestCounter twice, and logging a message: the response tells which counter it
 * used, and how many times.
 */
final readonly class CountingHandler implements RequestHandlerInterface
{

    public function __construct(
        private RequestCounter $first,
        private RequestCounter $second,
        private LoggerInterface $logger
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->first->count++;
        $this->second->count++;

        $this->logger->info('Counting');

        return new JsonResponse([
            'counter' => spl_object_id($this->first),
            'count' => $this->second->count,
            'path' => $request->getUri()->getPath(),
            'query' => $request->getQueryParams(),
        ]);
    }
}
