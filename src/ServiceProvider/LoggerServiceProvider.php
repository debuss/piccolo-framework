<?php declare(strict_types=1);

namespace Piccolo\ServiceProvider;

use Borsch\Config\Config;
use InvalidArgumentException;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\{Level, Logger};
use Monolog\Processor\PsrLogMessageProcessor;
use Piccolo\ConfigValue;
use Piccolo\Container\{ContainerBuilder, ServiceProvider};
use Piccolo\Environment;
use Piccolo\Http\RequestIdProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\{LoggerAwareInterface, LoggerInterface};

/**
 * PSR-3 Logger Service Provider
 *
 * Logs are written as JSON lines to a single stream, configured with environment variables:
 * - LOG_STREAM: `php://stderr` by default (Docker, systemd, ...), or a file path (relative to the app root)
 * - LOG_LEVEL: minimum level to log, `debug` by default, `info` in production
 *
 * The records logged while handling a request have its identifier in their `extra` (see RequestIdMiddleware).
 *
 * The logger is a singleton, reset after each request in a worker (buffers of the handlers, processors).
 *
 * @see https://seldaek.github.io/monolog/
 */
class LoggerServiceProvider implements ServiceProvider
{

    public function register(ContainerBuilder $builder): void
    {
        // Singleton, as the RequestIdMiddleware sets the identifier of the request on the instance given to the logger
        $builder->singleton(RequestIdProcessor::class);

        $builder->singleton(
            Logger::class,
            static function (Config $config, Environment $environment, RequestIdProcessor $requestIdProcessor): Logger {
                $level = self::level(ConfigValue::string($config, 'LOG_LEVEL', $environment->isProduction() ? 'info' : 'debug'));

                $stream = ConfigValue::string($config, 'LOG_STREAM', 'php://stderr');
                if (!str_contains($stream, '://') && !preg_match('#^([a-z]:)?[\\\\/]#i', $stream)) {
                    $stream = app_path($stream);
                }

                return new Logger(
                    ConfigValue::string($config, 'LOGGER_NAME', 'app'),
                    [new StreamHandler($stream, $level)->setFormatter(new JsonFormatter())],
                    [new PsrLogMessageProcessor(removeUsedContextFields: true), $requestIdProcessor]
                );
            }
        );
        $builder->alias(LoggerInterface::class, Logger::class);

        $builder->onReset(Logger::class, static fn (Logger $logger) => $logger->reset());

        // For every class implementing the LoggerAwareInterface, inject the logger instance into the class and change
        // the name to the class name so that you know from where the log is originated.
        $builder->inflect(
            LoggerAwareInterface::class,
            static function (LoggerAwareInterface $service, ContainerInterface $container): void {
                $logger = $container->get(LoggerInterface::class);
                if ($logger instanceof Logger) {
                    $logger = $logger->withName($service::class);
                }

                $service->setLogger($logger);
            }
        );
    }

    /**
     * @throws InvalidArgumentException When LOG_LEVEL is not a level of PSR-3 (debug, info, notice, ...).
     */
    private static function level(string $name): Level
    {
        $name = strtoupper(trim($name));

        if (!in_array($name, Level::NAMES, true)) {
            throw new InvalidArgumentException(sprintf(
                'LOG_LEVEL must be one of %s, "%s" given',
                strtolower(implode(', ', Level::NAMES)),
                strtolower($name)
            ));
        }

        return Level::fromName($name);
    }
}
