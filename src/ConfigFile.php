<?php declare(strict_types=1);

namespace Piccolo;

use Borsch\Config\Config;
use Closure;
use UnexpectedValueException;

/**
 * Loads the files of the config directory, checking what they return: a mistake in one of them fails with its name.
 *
 * @internal Used by the kernel and the console commands.
 */
final class ConfigFile
{

    /**
     * config/container.php, pipeline.php and routes.php return a closure.
     */
    public static function closure(string $file): Closure
    {
        $value = require $file;

        return $value instanceof Closure ? $value : throw self::unexpected($file, 'a closure', $value);
    }

    /**
     * config/configuration.php returns the configuration.
     */
    public static function config(string $file): Config
    {
        $value = require $file;

        return $value instanceof Config ? $value : throw self::unexpected($file, 'a ' . Config::class, $value);
    }

    /**
     * config/commands.php returns the classes of the commands, by name.
     *
     * @return array<string, string>
     */
    public static function commands(string $file): array
    {
        $value = require $file;

        if (!is_array($value)) {
            throw self::unexpected($file, 'an array of command classes by name', $value);
        }

        $commands = [];
        foreach ($value as $name => $class) {
            if (!is_string($name) || !is_string($class)) {
                throw self::unexpected($file, 'an array of command classes by name', $value);
            }

            $commands[$name] = $class;
        }

        return $commands;
    }

    private static function unexpected(string $file, string $expected, mixed $value): UnexpectedValueException
    {
        return new UnexpectedValueException(
            sprintf('%s must return %s, %s returned', $file, $expected, get_debug_type($value))
        );
    }
}
