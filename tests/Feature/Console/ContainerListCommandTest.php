<?php declare(strict_types=1);

use Piccolo\Paths;
use Symfony\Component\Console\Command\Command;

afterEach(fn () => Paths::setCurrent(new Paths(dirname(__DIR__, 3))));

test('lists the services with their lifetime and how they are created, the aliases and the inflectors', function () {
    $tester = command(fixtureApp(), 'container:list');

    expect($tester->execute([]))->toBe(Command::SUCCESS)
        ->and(display($tester))
        ->toContain('Tests\Fixtures\RequestCounter scoped autowired')
        ->toContain('Monolog\Logger singleton factory (config/container.php:22)')
        ->toContain('Piccolo\Environment singleton instance of Piccolo\Environment')
        ->toContain('Mezzio\Router\RouterInterface Mezzio\Router\FastRouteRouter')
        ->toContain('Psr\Log\LoggerAwareInterface')
        ->toContain('autowired as transient services');
});

test('only lists the services, aliases and types containing the filter', function () {
    $tester = command(fixtureApp(), 'container:list');

    expect($tester->execute(['filter' => 'ROUTER']))->toBe(Command::SUCCESS)
        ->and(display($tester))
        ->toContain('Mezzio\Router\FastRouteRouter')
        ->toContain('Mezzio\Router\RouterInterface')
        ->not->toContain('Monolog\Logger')
        ->not->toContain('Inflectors');
});
