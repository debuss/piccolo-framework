<?php declare(strict_types=1);

namespace Piccolo\Http\Exception;

use Throwable;

/**
 * 409: The request conflicts with the current state of the resource, e.g. a duplicate.
 */
class ConflictException extends HttpException
{

    /**
     * @param array<string, mixed> $additional
     */
    public function __construct(string $detail = '', array $additional = [], ?Throwable $previous = null)
    {
        parent::__construct(409, $detail, $additional, previous: $previous);
    }
}
