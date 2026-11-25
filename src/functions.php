<?php declare(strict_types=1);

use Piccolo\Paths;

if (!function_exists('app_path')) {
    /**
     * Returns the path to the root app directory, appended with extra path.
     */
    function app_path(string ...$paths): string
    {
        return Paths::current()->root(...$paths);
    }
}

if (!function_exists('source_path')) {
    /**
     * Returns the path to the source directory, appended with extra path.
     */
    function source_path(string ...$paths): string
    {
        return Paths::current()->source(...$paths);
    }
}

if (!function_exists('config_path')) {
    /**
     * Returns the path to the config directory, appended with extra path.
     */
    function config_path(string ...$paths): string
    {
        return Paths::current()->config(...$paths);
    }
}

if (!function_exists('storage_path')) {
    /**
     * Returns the path to the storage directory, appended with extra path.
     */
    function storage_path(string ...$paths): string
    {
        return Paths::current()->storage(...$paths);
    }
}

if (!function_exists('cache_path')) {
    /**
     * Returns the path to the cache directory, appended with extra path.
     */
    function cache_path(string ...$paths): string
    {
        return Paths::current()->cache(...$paths);
    }
}

if (!function_exists('logs_path')) {
    /**
     * Returns the path to the logs directory, appended with extra path.
     */
    function logs_path(string ...$paths): string
    {
        return Paths::current()->logs(...$paths);
    }
}
