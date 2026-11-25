<?php declare(strict_types=1);

namespace Piccolo\Runtime;

use Closure;
use Debuss\ServerRequestFactory\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\{EmitterInterface, SapiEmitter};
use Piccolo\Container\Container;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * FrankenPHP Worker Runner
 *
 * Handles the requests of a FrankenPHP worker: the application is booted once (container, pipeline, routes), then each
 * request is handled in the loop, with the superglobals FrankenPHP sets for it.
 *
 * After each request, the singletons holding a state are reset (see Container::reset()) and the garbage collector
 * runs. The worker stops after MAX_REQUESTS requests (unlimited by default) so that FrankenPHP restarts it, to free the
 * memory leaked by extensions or libraries.
 *
 * Each request gets its own container scope from the Application: scoped services never outlive their request.
 *
 * @see https://frankenphp.dev/docs/worker/
 */
readonly class FrankenPhpWorkerRunner extends SapiRunner
{

    /**
     * @param int $maxRequests The number of requests after which the worker stops, 0 for unlimited.
     * @param Closure|null $handleRequest frankenphp_handle_request() by default, replaced in the tests.
     */
    public function __construct(
        ServerRequestFactory $serverRequestFactory,
        private Container $root,
        private int $maxRequests = 0,
        EmitterInterface $emitter = new SapiEmitter(),
        private ?Closure $handleRequest = null
    ) {
        parent::__construct($serverRequestFactory, $root, $emitter);
    }

    /**
     * Whether the script is run by a FrankenPHP worker.
     */
    public static function isSupported(): bool
    {
        return function_exists('frankenphp_handle_request') && !empty($_SERVER['FRANKENPHP_WORKER']);
    }

    public function run(RequestHandlerInterface $handler): void
    {
        $runner = $this->runner($handler);
        $handleRequest = $this->handleRequest ?? frankenphp_handle_request(...);

        for ($handled = 0; $this->maxRequests === 0 || $handled < $this->maxRequests; $handled++) {
            $keepRunning = $handleRequest($runner->run(...));

            $this->root->reset();
            gc_collect_cycles();

            if (!$keepRunning) {
                break;
            }
        }
    }
}
