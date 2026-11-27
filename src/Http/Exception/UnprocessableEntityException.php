<?php declare(strict_types=1);

namespace Piccolo\Http\Exception;

use Throwable;

/**
 * 422: The request is well-formed but its content is invalid, e.g. validation errors in
 * the additional data.
 */
class UnprocessableEntityException extends HttpException
{

    /**
     * @param array<string, mixed> $additional
     */
    public function __construct(string $detail = '', array $additional = [], ?Throwable $previous = null)
    {
        parent::__construct(422, $detail, $additional, previous: $previous);
    }
}
