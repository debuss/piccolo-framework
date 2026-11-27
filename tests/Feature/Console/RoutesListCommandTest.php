<?php declare(strict_types=1);

use Piccolo\Paths;
use Symfony\Component\Console\Command\Command;

afterEach(fn () => Paths::setCurrent(new Paths(dirname(__DIR__, 3))));

test('lists the routes of config/routes.php, with the middlewares of each route followed by its handler', function () {
    $tester = command(fixtureApp(), 'routes:list');

    expect($tester->execute([]))->toBe(Command::SUCCESS)
        ->and($tester->getDisplay())
        ->toContain('count')
        ->toContain('Tests\Fixtures\CountingHandler')
        // A route with a middleware
        ->toContain('cached')
        ->toContain('Piccolo\Middleware\HttpCacheMiddleware → Tests\Fixtures\NoopRequestHandler')
        ->toContain('/posts/{id:\d+}');
});

test('lists the routes once, when the HTTP application is already loaded', function () {
    $kernel = Piccolo\Kernel::boot(fixtureApp());
    $kernel->http();
    $tester = new Symfony\Component\Console\Tester\CommandTester($kernel->console()->find('routes:list'));

    expect($tester->execute([]))->toBe(Command::SUCCESS)
        ->and(substr_count($tester->getDisplay(), 'Tests\Fixtures\CountingHandler'))->toBe(1);
});
