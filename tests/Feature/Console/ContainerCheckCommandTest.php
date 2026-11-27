<?php declare(strict_types=1);

use Piccolo\Paths;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

afterEach(fn () => Paths::setCurrent(new Paths(dirname(__DIR__, 3))));

test('resolves the services, and the middlewares and handlers of the pipeline and of the routes', function () {
    $tester = command(fixtureApp(), 'container:check');

    expect($tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]))->toBe(Command::SUCCESS)
        ->and(display($tester))
        ->toContain('✔ Psr\Log\LoggerInterface')
        ->toContain('✔ Tests\Fixtures\RequestCounter')
        // Piped on a path
        ->toContain('✔ Tests\Fixtures\NoopMiddleware')
        // Handlers and middlewares of the routes
        ->toContain('✔ Tests\Fixtures\CountingHandler')
        ->toContain('✔ Tests\Fixtures\NoopRequestHandler')
        ->toMatch('/The \d+ services, middlewares and handlers can be resolved/');
});

test('fails with the services that cannot be resolved, and why', function () {
    $tester = command(brokenApp(), 'container:check');

    expect($tester->execute([]))->toBe(Command::FAILURE)
        ->and(display($tester))
        ->toContain('✘ Tests\Fixtures\CountingHandler')
        ->toContain('The scoped service "Tests\Fixtures\RequestCounter" cannot be resolved outside a scope')
        ->toContain('✘ missing')
        ->toContain('"Tests\Fixtures\MissingService" is neither a registered service nor an existing class')
        ->toContain('✘ Tests\Fixtures\UnresolvableHandler')
        ->toContain('Unable to resolve the parameter $name')
        ->toMatch('/3 of the \d+ services cannot be resolved/')
        ->not->toContain('✘ Psr\Log\LoggerInterface');
});

test('leaves out the excluded services', function () {
    $tester = command(brokenApp(), 'container:check');

    $status = $tester->execute(['--exclude' => [
        'Tests\Fixtures\CountingHandler',
        'missing',
        'Tests\Fixtures\UnresolvableHandler',
    ]]);

    expect($status)->toBe(Command::SUCCESS);
});
