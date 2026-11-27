<?php declare(strict_types=1);

use Piccolo\Environment;
use Piccolo\Http\{RequestIdProcessor, ServerRequestErrorResponseGenerator};
use Debuss\ServerRequestFactory\BadRequestException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Monolog\Handler\TestHandler;
use Monolog\Logger;

beforeEach(fn () => $this->server = $_SERVER);
afterEach(fn () => $_SERVER = $this->server);

function makeServerRequestErrorResponseGenerator(Environment $environment, ?TestHandler $handler = null): ServerRequestErrorResponseGenerator
{
    $processor = new RequestIdProcessor();

    return new ServerRequestErrorResponseGenerator(
        new Psr17Factory(),
        new Logger('test', [$handler ?? new TestHandler()], [$processor]),
        $environment,
        $processor
    );
}

test('returns a 400 plain text response for a bad request', function () {
    $response = makeServerRequestErrorResponseGenerator(Environment::Production)(new BadRequestException('boom'));

    expect($response->getStatusCode())->toBe(400)
        ->and($response->getHeaderLine('Content-Type'))->toBe('text/plain; charset=utf-8');
});

test('only returns the reason phrase in production', function () {
    $response = makeServerRequestErrorResponseGenerator(Environment::Production)(
        new BadRequestException('Invalid header value for X-Token: secret-abc')
    );

    expect((string)$response->getBody())->toBe('Bad Request');
});

test('returns the exception outside of production', function () {
    $response = makeServerRequestErrorResponseGenerator(Environment::Development)(
        new BadRequestException('Invalid header value')
    );

    expect((string)$response->getBody())->toContain('BadRequestException: Invalid header value');
});

test('logs a bad request as a warning', function () {
    $handler = new TestHandler();
    $exception = new BadRequestException('Invalid header value');

    makeServerRequestErrorResponseGenerator(Environment::Production, $handler)($exception);

    expect($handler->hasWarningThatContains('Invalid header value'))->toBeTrue()
        ->and($handler->getRecords()[0]->context['exception'])->toBe($exception);
});

test('returns a 500 plain text response for any other error', function () {
    $response = makeServerRequestErrorResponseGenerator(Environment::Production)(
        new InvalidArgumentException('Invalid value in uploaded files specification.')
    );

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getHeaderLine('Content-Type'))->toBe('text/plain; charset=utf-8')
        ->and((string)$response->getBody())->toBe('Internal Server Error');
});

test('logs any other error as an error', function () {
    $handler = new TestHandler();
    $exception = new RuntimeException('Unable to read the uploaded file');

    makeServerRequestErrorResponseGenerator(Environment::Production, $handler)($exception);

    expect($handler->hasErrorThatContains('Unable to read the uploaded file'))->toBeTrue()
        ->and($handler->hasWarningRecords())->toBeFalse()
        ->and($handler->getRecords()[0]->context['exception'])->toBe($exception);
});

test('identifies the request with the X-Request-Id header of the proxy, in the response and the log', function () {
    $_SERVER['HTTP_X_REQUEST_ID'] = 'proxy-id-42';
    $handler = new TestHandler();

    $response = makeServerRequestErrorResponseGenerator(Environment::Production, $handler)(new BadRequestException('boom'));

    expect($response->getHeaderLine('X-Request-Id'))->toBe('proxy-id-42')
        ->and($handler->getRecords()[0]->extra['request_id'])->toBe('proxy-id-42');
});

test('generates an identifier when the header is missing or invalid, and does not keep it for the next logs', function (?string $header) {
    if ($header === null) {
        unset($_SERVER['HTTP_X_REQUEST_ID']);
    } else {
        $_SERVER['HTTP_X_REQUEST_ID'] = $header;
    }
    $handler = new TestHandler();
    $processor = new RequestIdProcessor();
    $logger = new Logger('test', [$handler], [$processor]);

    $response = new ServerRequestErrorResponseGenerator(new Psr17Factory(), $logger, Environment::Production, $processor)(
        new RuntimeException('boom')
    );
    $logger->info('Next');

    expect($response->getHeaderLine('X-Request-Id'))->toMatch('/^[0-9a-f]{32}$/')
        ->and($handler->getRecords()[0]->extra['request_id'])->toBe($response->getHeaderLine('X-Request-Id'))
        ->and($handler->getRecords()[1]->extra)->toBe([]);
})->with([
    'missing' => [null],
    'with a line break' => ["forged\nline"],
]);
