<?php declare(strict_types=1);

namespace Tests\Fixtures;

use Piccolo\Container\Disposable;

/**
 * A scoped service: counts the times it is used during a request, and records its disposal.
 */
final class RequestCounter implements Disposable
{

    public static array $disposed = [];

    public int $count = 0;

    public function dispose(): void
    {
        self::$disposed[] = spl_object_id($this);
    }
}
