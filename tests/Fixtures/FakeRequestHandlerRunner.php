<?php declare(strict_types=1);

namespace Tests\Fixtures;

use Piccolo\Runtime\RunnerInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Records the handler it was run with, so tests can assert Application::run() delegates to it without actually
 * marshalling a request from PHP globals and emitting a response.
 */
final class FakeRequestHandlerRunner implements RunnerInterface
{

    public ?RequestHandlerInterface $handler = null;

    public bool $wasRun = false;

    public function run(RequestHandlerInterface $handler): void
    {
        $this->wasRun = true;
        $this->handler = $handler;
    }
}
