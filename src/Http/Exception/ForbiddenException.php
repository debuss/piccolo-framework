<?php declare(strict_types=1);

namespace Piccolo\Http\Exception;

use Throwable;

/**
 * 403: The client is authenticated but not allowed to do this.
 */
class ForbiddenException extends HttpException
{

    /**
     * @param array<string, mixed> $additional
     */
    public function __construct(string $detail = '', array $additional = [], ?Throwable $previous = null)
    {
        parent::__construct(403, $detail, $additional, previous: $previous);
    }
}
