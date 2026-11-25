<?php declare(strict_types=1);

namespace Piccolo;

use Composer\InstalledVersions;
use RuntimeException;

/**
 * Paths
 *
 * The directories of the application, from its root directory:
 *
 *  .
 *  ├── config/
 *  ├── src/
 *  └── storage/
 *      ├── cache/
 *      └── logs/
 *
 * The Kernel sets the current paths when it boots, they are used by the global helpers (app_path(), config_path(), ...).
 * Without a Kernel (tests, scripts), the root directory is the one of the Composer root package.
 */
final class Paths
{

    private static ?self $current = null;

    public readonly string $root;

    public function __construct(string $root)
    {
        $realpath = realpath($root);
        if ($realpath === false || !is_dir($realpath)) {
            throw new RuntimeException(sprintf('The root directory "%s" does not exist.', $root));
        }

        $this->root = rtrim($realpath, '\\/');
    }

    /**
     * The paths used by the global helpers.
     */
    public static function current(): self
    {
        return self::$current ??= new self(InstalledVersions::getRootPackage()['install_path']);
    }

    public static function setCurrent(self $paths): self
    {
        return self::$current = $paths;
    }

    /**
     * The root directory, appended with the given paths.
     */
    public function root(string ...$paths): string
    {
        return array_reduce(
            $paths,
            static fn (string $path, string $segment) => $path . DIRECTORY_SEPARATOR . ltrim($segment, ' /\\'),
            $this->root
        );
    }

    public function source(string ...$paths): string
    {
        return $this->root('src', ...$paths);
    }

    public function config(string ...$paths): string
    {
        return $this->root('config', ...$paths);
    }

    public function storage(string ...$paths): string
    {
        return $this->root('storage', ...$paths);
    }

    public function cache(string ...$paths): string
    {
        return $this->storage('cache', ...$paths);
    }

    public function logs(string ...$paths): string
    {
        return $this->storage('logs', ...$paths);
    }
}
