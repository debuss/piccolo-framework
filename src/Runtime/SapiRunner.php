<?php declare(strict_types=1);

namespace Piccolo\Runtime;

use Debuss\ServerRequestFactory\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\{EmitterInterface, SapiEmitter};
use Laminas\HttpHandlerRunner\RequestHandlerRunner;
use Piccolo\Http\ServerRequestErrorResponseGenerator;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * SAPI Runner
 *
 * Handles the single request of a classic PHP process (PHP-FPM, Apache mod_php, the built-in web server, FrankenPHP
 * without worker): the server request is created from the globals, then the response is emitted.
 *
 * When the server request cannot be created (invalid header, malformed uploaded files, ...), the response comes from
 * the ServerRequestErrorResponseGenerator, only resolved in that case to avoid building its dependencies on each
 * request.
 *
 * @see https://docs.laminas.dev/laminas-httphandlerrunner/runner/
 */
readonly class SapiRunner implements RunnerInterface
{

    public function __construct(
        private ServerRequestFactory $serverRequestFactory,
        private ContainerInterface $container,
        private EmitterInterface $emitter = new SapiEmitter()
    ) {}

    public function run(RequestHandlerInterface $handler): void
    {
        $this->runner($handler)->run();
    }

    protected function runner(RequestHandlerInterface $handler): RequestHandlerRunner
    {
        return new RequestHandlerRunner(
            $handler,
            $this->emitter,
            $this->serverRequestFactory->fromGlobals(...),
            fn (Throwable $e): ResponseInterface => $this->container->get(ServerRequestErrorResponseGenerator::class)($e)
        );
    }
}
