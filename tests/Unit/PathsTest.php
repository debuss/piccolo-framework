<?php declare(strict_types=1);

use Piccolo\Paths;

afterEach(fn () => Paths::setCurrent(new Paths(dirname(__DIR__, 2))));

test('builds the paths of the application from its root directory', function () {
    $root = realpath(fixtureApp());
    $paths = new Paths(fixtureApp() . '/');

    expect($paths->root)->toBe($root)
        ->and($paths->root('public', '/index.php'))->toBe($root . '/public/index.php')
        ->and($paths->source('Domain'))->toBe($root . '/src/Domain')
        ->and($paths->config())->toBe($root . '/config')
        ->and($paths->storage('openapi.yaml'))->toBe($root . '/storage/openapi.yaml')
        ->and($paths->cache('routes.cache.php'))->toBe($root . '/storage/cache/routes.cache.php')
        ->and($paths->logs())->toBe($root . '/storage/logs');
});

test('refuses a root directory that does not exist', function () {
    expect(fn () => new Paths('/does/not/exist'))->toThrow(RuntimeException::class, 'does not exist');
});

test('the helpers use the current paths, the Composer root package by default', function () {
    expect(app_path())->toBe(realpath(dirname(__DIR__, 2)));

    Paths::setCurrent(new Paths(fixtureApp()));

    expect(app_path('.env'))->toBe(realpath(fixtureApp()) . '/.env')
        ->and(source_path())->toBe(realpath(fixtureApp()) . '/src')
        ->and(config_path('routes.php'))->toBe(realpath(fixtureApp()) . '/config/routes.php')
        ->and(storage_path())->toBe(realpath(fixtureApp()) . '/storage')
        ->and(cache_path())->toBe(realpath(fixtureApp()) . '/storage/cache')
        ->and(logs_path())->toBe(realpath(fixtureApp()) . '/storage/logs');
});
