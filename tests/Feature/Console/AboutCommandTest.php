<?php declare(strict_types=1);

use Piccolo\Paths;
use Symfony\Component\Console\Command\Command;

afterEach(fn () => Paths::setCurrent(new Paths(dirname(__DIR__, 3))));

test('describes the application and its environment', function () {
    $tester = command(fixtureApp(), 'about');

    expect($tester->execute([]))->toBe(Command::SUCCESS)
        ->and(display($tester))
        ->toMatch('/Framework \S+/')
        ->toMatch('/Container \d+\.\d+\.\d+/')
        ->toContain('Environment development')
        ->toContain('Root directory ' . realpath(fixtureApp()))
        ->toContain('Timezone Europe/Paris')
        ->toContain('PSR-7 implementation Nyholm\Psr7\Factory\Psr17Factory')
        ->toContain('Directory storage/cache')
        ->toContain('Used no, only in production')
        ->toContain('Version ' . PHP_VERSION);
});
