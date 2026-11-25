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
