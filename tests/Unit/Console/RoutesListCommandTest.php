<?php declare(strict_types=1);

use Laminas\Stratigility\MiddlewarePipe;
use Mezzio\Router\RouteCollector;
use Piccolo\Application;
use Piccolo\Console\RoutesListCommand;
use Piccolo\Paths;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Fixtures\{FakeContainer, FakeRequestHandlerRunner, FakeRouter};

afterEach(fn () => Paths::setCurrent(new Paths(dirname(__DIR__, 3))));

test('lists the routes of config/routes.php, with the middlewares of each route followed by its handler', function () {
    Paths::setCurrent(new Paths(fixtureApp()));

    $container = new FakeContainer();
    $application = new Application(
        new MiddlewarePipe(),
        new FakeRequestHandlerRunner(),
        new RouteCollector(new FakeRouter()),
        $container
    );

    $tester = new CommandTester(new RoutesListCommand($application, $container));

    expect($tester->execute([]))->toBe(Command::SUCCESS);

    expect($tester->getDisplay())
        ->toContain('count')
        ->toContain('Tests\Fixtures\CountingHandler')
        // A route with a middleware
        ->toContain('cached')
        ->toContain('Piccolo\Middleware\HttpCacheMiddleware → Tests\Fixtures\NoopRequestHandler');
});
