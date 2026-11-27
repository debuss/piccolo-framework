<?php declare(strict_types=1);

namespace Piccolo\Http\Exception;

use Throwable;

/**
 * 400: The request is malformed or invalid, e.g. a parameter of a wrong type.
 */
class BadRequestException extends HttpException
{

    /**
     * @param array<string, mixed> $additional
     */
    public function __construct(string $detail = '', array $additional = [], ?Throwable $previous = null)
    {
        parent::__construct(400, $detail, $additional, previous: $previous);
    }
}
