<?php declare(strict_types=1);

use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

/**
 * The root directory of the application used by the tests of the kernel, the console and the worker.
 */
function fixtureApp(): string
{
    return __DIR__ . '/Fixtures/app';
}

/**
 * An application whose services, middlewares and handlers cannot all be resolved, for container:check.
 */
function brokenApp(): string
{
    return __DIR__ . '/Fixtures/broken-app';
}

/**
 * A command of the console of the application, ready to be executed.
 */
function command(string $root, string $name): Symfony\Component\Console\Tester\CommandTester
{
    return new Symfony\Component\Console\Tester\CommandTester(Piccolo\Kernel::boot($root)->console()->find($name));
}

/**
 * The display of a command, on a single line: the long lines are wrapped by the console.
 */
function display(Symfony\Component\Console\Tester\CommandTester $tester): string
{
    return preg_replace('/\s+/', ' ', $tester->getDisplay());
}
