<?php declare(strict_types=1);

use Borsch\Config\Config;
use Piccolo\{ConfigFile, ConfigValue};

/**
 * A config file returning the given PHP code.
 */
function configFile(string $code): string
{
    $file = tempnam(sys_get_temp_dir(), 'piccolo-config-');
    file_put_contents($file, "<?php\nreturn $code;");

    return $file;
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir() . '/piccolo-config-*') ?: [] as $file) {
        unlink($file);
    }
});

test('loads the closure, the configuration and the commands of the config files', function () {
    expect(ConfigFile::closure(configFile('fn () => 42'))())->toBe(42)
        ->and(ConfigFile::config(configFile("new Borsch\\Config\\Config(['APP_ENV' => 'production'])")))->toBeInstanceOf(Config::class)
        ->and(ConfigFile::commands(configFile("['app:noop' => 'App\\\\NoopCommand']")))->toBe(['app:noop' => 'App\NoopCommand']);
});

test('fails with the name of the file when it does not return what is expected', function (string $method, string $code, string $message) {
    $file = configFile($code);

    expect(fn () => ConfigFile::$method($file))->toThrow(UnexpectedValueException::class, "$file must return $message");
})->with([
    'not a closure' => ['closure', '[]', 'a closure, array returned'],
    'not a configuration' => ['config', "['APP_ENV' => 'production']", 'a Borsch\Config\Config, array returned'],
    'not an array of commands' => ['commands', "'App\\\\NoopCommand'", 'an array of command classes by name, string returned'],
    'a command without name' => ['commands', "['App\\\\NoopCommand']", 'an array of command classes by name, array returned'],
]);

test('reads the values of the configuration as strings', function () {
    $config = new Config(['NAME' => 'app', 'PORT' => 8080, 'DEBUG' => true, 'LIST' => ['a'], 'NONE' => null]);

    expect(ConfigValue::string($config, 'NAME'))->toBe('app')
        ->and(ConfigValue::string($config, 'PORT'))->toBe('8080')
        ->and(ConfigValue::string($config, 'DEBUG'))->toBe('true')
        ->and(ConfigValue::string($config, 'LIST', 'default'))->toBe('default')
        ->and(ConfigValue::string($config, 'NONE', 'default'))->toBe('default')
        ->and(ConfigValue::string($config, 'MISSING', 'default'))->toBe('default');
});
