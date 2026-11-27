<?php declare(strict_types=1);

use Piccolo\Http\ErrorLogListener;
use Nyholm\Psr7\{Response, ServerRequest};
use Monolog\Handler\TestHandler;
use Monolog\Logger;

test('logs the error with the request method, URI and response status', function () {
    $handler = new TestHandler();
    $exception = new RuntimeException('boom');

    new ErrorLogListener(new Logger('test', [$handler]))(
        $exception,
        new ServerRequest('POST', 'https://example.com/api/posts?page=2'),
        new Response(500)
    );

    $record = $handler->getRecords()[0];

    expect($handler->hasErrorThatContains('boom'))->toBeTrue()
        ->and($record->context)->toBe([
            'exception' => $exception,
            'request' => ['method' => 'POST', 'uri' => 'https://example.com/api/posts?page=2'],
            'response' => ['status' => 500],
        ]);
});

test('logs the original URI when available', function () {
    $handler = new TestHandler();

    // As seen by a middleware piped on "/api", with the original URI kept by OriginalMessages
    $request = new ServerRequest('GET', '/v1/posts/1')
        ->withAttribute('originalUri', new ServerRequest('GET', '/api/v1/posts/1')->getUri());

    new ErrorLogListener(new Logger('test', [$handler]))(new RuntimeException('boom'), $request, new Response());

    expect($handler->getRecords()[0]->context['request']['uri'])->toBe('/api/v1/posts/1');
});

test('does not log headers, cookies nor body', function () {
    $handler = new TestHandler();

    $request = new ServerRequest('POST', '/login', ['Authorization' => 'Bearer secret-token'])
        ->withCookieParams(['session' => 'secret-session'])
        ->withParsedBody(['password' => 'secret-password']);

    new ErrorLogListener(new Logger('test', [$handler]))(new RuntimeException('boom'), $request, new Response());

    $context = json_encode($handler->getRecords()[0]->context['request']);

    expect($context)->not->toContain('secret');
});

test('logs the errors of the client (4xx) as warnings', function (int $status, string $level) {
    $handler = new TestHandler();

    new ErrorLogListener(new Logger('test', [$handler]))(
        new RuntimeException('boom'),
        new ServerRequest('GET', '/api/posts/1'),
        new Response($status)
    );

    expect($handler->getRecords()[0]->level->getName())->toBe($level);
})->with([
    [404, 'WARNING'],
    [422, 'WARNING'],
    [500, 'ERROR'],
    [503, 'ERROR'],
]);
