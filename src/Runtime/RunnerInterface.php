<?php declare(strict_types=1);

namespace Piccolo\Runtime;

use Psr\Http\Server\RequestHandlerInterface;

/**
 * Runs the application: creates the server requests from the globals, handles them, and emits the responses.
 */
interface RunnerInterface
{

    public function run(RequestHandlerInterface $handler): void;
}
