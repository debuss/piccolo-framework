<?php declare(strict_types=1);

namespace Piccolo;

use Borsch\Config\Config;

/**
 * Reads the values of the configuration as strings: they come from the .env file and the environment variables, or
 * from config/configuration.php where they can be of any scalar type.
 *
 * @internal Used by the kernel and the service providers.
 */
final class ConfigValue
{

    /**
     * The value as a string, or the default value when it is missing or not a scalar.
     */
    public static function string(Config $config, string $id, string $default = ''): string
    {
        $value = $config->getOrDefault($id, $default);

        return match (true) {
            is_string($value) => $value,
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string)$value,
            default => $default,
        };
    }
}
