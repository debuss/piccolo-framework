<?php declare(strict_types=1);

use Borsch\Config\Config;
use Monolog\Handler\BufferHandler;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Piccolo\Container\{Container, ContainerBuilder};
use Piccolo\Environment;
use Piccolo\Http\RequestIdProcessor;
use Piccolo\ServiceProvider\LoggerServiceProvider;
use Psr\Log\{LoggerAwareInterface, LoggerAwareTrait, LoggerInterface};

function makeLoggerContainer(?Closure $register = null): Container
{
    $builder = new ContainerBuilder()
        ->instance(Config::class, new Config(['LOG_STREAM' => 'php://memory']))
        ->instance(Environment::class, Environment::Development)
        ->addProvider(new LoggerServiceProvider());

    if ($register !== null) {
        $register($builder);
    }

    return $builder->build();
}

test('gives the logger the singleton RequestIdProcessor, on which the middleware sets the identifier', function () {
    $container = makeLoggerContainer();

    $processor = $container->get(RequestIdProcessor::class);

    expect($container->get(RequestIdProcessor::class))->toBe($processor)
        ->and($container->get(Logger::class)->getProcessors())->toContain($processor)
        ->and($container->get(LoggerInterface::class))->toBe($container->get(Logger::class));
});

test('gives the logger aware services a logger named after their class', function () {
    $service = new class implements LoggerAwareInterface {
        use LoggerAwareTrait;

        public function logger(): ?LoggerInterface
        {
            return $this->logger;
        }
    };

    $container = makeLoggerContainer(fn (ContainerBuilder $builder) => $builder->singleton('service', fn () => clone $service));

    expect($container->get('service')->logger()->getName())->toBe($service::class);
});

test('resets the logger and the request identifier when the container is reset', function () {
    $handler = new TestHandler();
    $handler->setSkipReset(true); // Kept by the reset, to read the flushed records
    $container = makeLoggerContainer(fn (ContainerBuilder $builder) => $builder->singleton(
        Logger::class,
        fn (RequestIdProcessor $processor) => new Logger('test', [new BufferHandler($handler)], [$processor])
    ));

    $container->get(RequestIdProcessor::class)->setRequestId('abc');
    $container->get(Logger::class)->info('buffered');

    expect($handler->getRecords())->toBe([]);

    $container->reset();

    // The buffer is flushed by the reset, and the identifier is not added to the next records
    expect($handler->getRecords())->toHaveCount(1)
        ->and($handler->getRecords()[0]->extra)->toBe(['request_id' => 'abc']);

    $container->get(Logger::class)->info('next request');
    $container->reset();

    expect($handler->getRecords()[1]->extra)->toBe([]);
});
