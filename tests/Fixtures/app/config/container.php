<?php declare(strict_types=1);

use Borsch\Config\Config;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Piccolo\Container\ContainerBuilder;
use Piccolo\Environment;
use Piccolo\Http\RequestIdProcessor;
use Tests\Fixtures\RequestCounter;

return static function (ContainerBuilder $builder, Config $config, Environment $environment): void {
    $builder->scoped(RequestCounter::class);

    // Replaces the logger of the framework
    // Its records are kept when the logger is reset after each request, to be read by the tests
    $builder->singleton(TestHandler::class, static function (): TestHandler {
        $handler = new TestHandler();
        $handler->setSkipReset(true);

        return $handler;
    });
    $builder->singleton(Logger::class, static fn (TestHandler $handler, RequestIdProcessor $processor) => new Logger('fixture', [$handler], [$processor]));

    $builder->instance('fixture.environment', $environment);
};
