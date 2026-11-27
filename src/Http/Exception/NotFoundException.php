<?php declare(strict_types=1);

namespace Piccolo\Http\Exception;

use Throwable;

/**
 * 404: The resource does not exist.
 */
class NotFoundException extends HttpException
{

    /**
     * @param array<string, mixed> $additional
     */
    public function __construct(string $detail = '', array $additional = [], ?Throwable $previous = null)
    {
        parent::__construct(404, $detail, $additional, previous: $previous);
    }
}
