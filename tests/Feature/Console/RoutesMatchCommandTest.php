<?php declare(strict_types=1);

use Piccolo\Paths;
use Symfony\Component\Console\Command\Command;

afterEach(fn () => Paths::setCurrent(new Paths(dirname(__DIR__, 3))));

test('tells the route matching a request, with its handler and the parameters of the path', function () {
    $tester = command(fixtureApp(), 'routes:match');

    expect($tester->execute(['method' => 'get', 'uri' => '/posts/42?page=2']))->toBe(Command::SUCCESS)
        ->and(display($tester))
        ->toContain('GET /posts/42?page=2 matches the route "post"')
        ->toContain('Path /posts/{id:\d+}')
        ->toContain('Middlewares and handler Tests\Fixtures\NoopRequestHandler')
        ->toContain('Parameters id = 42');
});

test('tells the middlewares of the matched route', function () {
    $tester = command(fixtureApp(), 'routes:match');

    expect($tester->execute(['method' => 'GET', 'uri' => '/cached']))->toBe(Command::SUCCESS)
        ->and(display($tester))
        ->toContain('Piccolo\Middleware\HttpCacheMiddleware → Tests\Fixtures\NoopRequestHandler')
        ->toContain('Parameters none');
});

test('fails with the allowed methods when the path matches with another method', function () {
    $tester = command(fixtureApp(), 'routes:match');

    expect($tester->execute(['method' => 'POST', 'uri' => '/count']))->toBe(Command::FAILURE)
        ->and(display($tester))->toContain('POST /count: 405 Method Not Allowed, the path only allows GET');
});

test('notes that HEAD and OPTIONS requests are answered by the implicit middlewares', function () {
    $tester = command(fixtureApp(), 'routes:match');

    expect($tester->execute(['method' => 'HEAD', 'uri' => '/count']))->toBe(Command::FAILURE)
        ->and(display($tester))->toContain('answers HEAD requests on the path anyway');
});

test('fails when no route matches the path', function () {
    $tester = command(fixtureApp(), 'routes:match');

    expect($tester->execute(['method' => 'GET', 'uri' => '/missing']))->toBe(Command::FAILURE)
        ->and(display($tester))->toContain('GET /missing: 404 Not Found');
});
