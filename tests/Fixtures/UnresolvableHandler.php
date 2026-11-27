<?php declare(strict_types=1);

namespace Tests\Fixtures;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A request handler the container cannot create: its builtin parameter has no default value.
 */
final readonly class UnresolvableHandler implements RequestHandlerInterface
{

    public function __construct(
        private string $name
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return JsonResponse::of(['name' => $this->name]);
    }
}
