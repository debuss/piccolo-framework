<?php declare(strict_types=1);

namespace Tests\Fixtures;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * A JSON response for the tests, Nyholm PSR-7 having no response with a JSON body.
 */
final class JsonResponse
{

    public static function of(array $data, int $status = 200): ResponseInterface
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($data, JSON_THROW_ON_ERROR));
    }
}
