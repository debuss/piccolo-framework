<?php declare(strict_types=1);

namespace Piccolo\Http\Exception;

use Throwable;

/**
 * 401: The request lacks valid credentials (not authenticated).
 */
class UnauthorizedException extends HttpException
{

    /**
     * @param array<string, mixed> $additional
     */
    public function __construct(string $detail = '', array $additional = [], ?Throwable $previous = null)
    {
        parent::__construct(401, $detail, $additional, previous: $previous);
    }
}
