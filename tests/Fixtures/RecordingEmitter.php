<?php declare(strict_types=1);

namespace Tests\Fixtures;

use Laminas\HttpHandlerRunner\Emitter\EmitterInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Keeps the emitted responses instead of sending them.
 */
final class RecordingEmitter implements EmitterInterface
{

    /** @var ResponseInterface[] */
    public array $responses = [];

    public function emit(ResponseInterface $response): bool
    {
        $this->responses[] = $response;

        return true;
    }
}
