<?php declare(strict_types=1);

namespace Piccolo\Http\Exception;

use InvalidArgumentException;
use Mezzio\ProblemDetails\Exception\{CommonProblemDetailsExceptionTrait, ProblemDetailsExceptionInterface};
use RuntimeException;
use Throwable;

/**
 * An error answered with an HTTP status (4xx or 5xx), thrown from a handler, a middleware or a service:
 *
 *  throw new NotFoundException(sprintf('The post %d does not exist', $id));
 *  throw new UnprocessableEntityException('The post is invalid', ['errors' => ['title' => 'Required']]);
 *  throw new HttpException(429, 'Too many requests, retry in a minute');
 *
 * Under the ProblemDetailsMiddleware (e.g. on /api), it becomes a Problem Details response (RFC 9457) with its status,
 * detail and additional data, in production too: the detail is meant for the client, it must not reveal internals.
 * Elsewhere, the ErrorHandler answers with its status (the code of the exception).
 *
 * The title is the reason phrase of the status, and the type a URI describing it, unless they are given.
 *
 * @see https://docs.mezzio.dev/mezzio-problem-details/
 */
class HttpException extends RuntimeException implements ProblemDetailsExceptionInterface
{

    use CommonProblemDetailsExceptionTrait;

    /**
     * @param int $status An error status, 4xx or 5xx.
     * @param string $detail The explanation of this occurrence of the error, for the client.
     * @param array<string, mixed> $additional Additional members of the problem details, e.g. ['errors' => [...]].
     *
     * @throws InvalidArgumentException When the status is not an error status.
     */
    public function __construct(
        int $status,
        string $detail = '',
        array $additional = [],
        string $title = '',
        string $type = '',
        ?Throwable $previous = null
    ) {
        if ($status < 400 || $status > 599) {
            throw new InvalidArgumentException(sprintf('An HTTP error status must be 4xx or 5xx, %d given', $status));
        }

        parent::__construct($detail, $status, $previous);

        $this->status = $status;
        $this->detail = $detail;
        $this->title = $title;
        $this->type = $type;
        $this->additional = $additional;
    }
}
