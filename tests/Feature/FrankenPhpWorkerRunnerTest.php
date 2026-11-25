<?php declare(strict_types=1);

use Debuss\ServerRequestFactory\ServerRequestFactory;
use Monolog\Handler\TestHandler;
use Piccolo\{Kernel, Paths};
use Piccolo\Runtime\FrankenPhpWorkerRunner;
use Tests\Fixtures\{RecordingEmitter, RequestCounter};

beforeEach(function () {
    $this->server = $_SERVER;
    $this->get = $_GET;
    $this->timezone = date_default_timezone_get();
    RequestCounter::$disposed = [];
});

afterEach(function () {
    $_SERVER = $this->server;
    $_GET = $this->get;
    date_default_timezone_set($this->timezone);
    Paths::setCurrent(new Paths(dirname(__DIR__, 2)));
});

/**
 * Simulates frankenphp_handle_request(): sets the superglobals of the next request, then calls the callback. Tells
 * whether the worker should keep running (there are more requests).
 *
 * @param string[] $paths
 */
function fakeFrankenPhp(array &$paths): Closure
{
    return function (callable $callback) use (&$paths): bool {
        $uri = array_shift($paths);
        parse_str((string)parse_url($uri, PHP_URL_QUERY), $_GET);
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => $uri,
            'QUERY_STRING' => (string)parse_url($uri, PHP_URL_QUERY),
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_HOST' => 'localhost',
        ];

        $callback();

        return $paths !== [];
    };
}

/**
 * @return array{Kernel, RecordingEmitter}
 */
function runWorker(array $paths, int $maxRequests = 0): array
{
    $kernel = Kernel::boot(fixtureApp());
    $container = $kernel->container();
    $emitter = new RecordingEmitter();

    $runner = new FrankenPhpWorkerRunner(
        $container->get(ServerRequestFactory::class),
        $container,
        $maxRequests,
        $emitter,
        fakeFrankenPhp($paths)
    );
    $runner->run($kernel->http());

    return [$kernel, $emitter];
}

test('is not supported outside FrankenPHP', function () {
    expect(FrankenPhpWorkerRunner::isSupported())->toBeFalse();
});

test('handles the requests until FrankenPHP stops the worker, each one in its own scope', function () {
    [, $emitter] = runWorker(['/count', '/count', '/missing']);

    $bodies = array_map(fn ($response) => json_decode((string)$response->getBody(), true), $emitter->responses);

    expect($emitter->responses)->toHaveCount(3)
        // The scoped counter is shared during a request, and is a new one for the next request
        ->and($bodies[0]['count'])->toBe(2)
        ->and($bodies[1]['count'])->toBe(2)
        ->and(RequestCounter::$disposed)->toHaveCount(2)
        ->and($emitter->responses[2]->getStatusCode())->toBe(404);
});

test('creates the server request of each request from the superglobals of that request', function () {
    [, $emitter] = runWorker(['/count?page=1', '/count?page=2']);

    expect(json_decode((string)$emitter->responses[0]->getBody(), true)['query'])->toBe(['page' => '1'])
        ->and(json_decode((string)$emitter->responses[1]->getBody(), true)['query'])->toBe(['page' => '2']);
});

test('does not log the identifier of a request with the next one', function () {
    [$kernel, $emitter] = runWorker(['/count', '/count']);

    $records = $kernel->container()->get(TestHandler::class)->getRecords();
    $requestIds = array_map(fn ($response) => $response->getHeaderLine('X-Request-Id'), $emitter->responses);

    expect($records)->toHaveCount(2)
        ->and($requestIds[0])->not->toBe($requestIds[1])
        ->and($records[0]->extra['request_id'])->toBe($requestIds[0])
        ->and($records[1]->extra['request_id'])->toBe($requestIds[1]);

    $kernel->container()->get(Psr\Log\LoggerInterface::class)->info('Outside a request');

    expect($records = $kernel->container()->get(TestHandler::class)->getRecords())->toHaveCount(3)
        ->and($records[2]->extra)->toBe([]);
});

test('stops after the maximum number of requests', function () {
    [, $emitter] = runWorker(['/count', '/count', '/count', '/count'], maxRequests: 2);

    expect($emitter->responses)->toHaveCount(2);
});
