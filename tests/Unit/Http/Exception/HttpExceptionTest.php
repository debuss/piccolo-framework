<?php declare(strict_types=1);

use Laminas\Stratigility\Middleware\{ErrorHandler, ErrorResponseGenerator};
use Mezzio\ProblemDetails\{ProblemDetailsMiddleware, ProblemDetailsResponseFactory};
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Piccolo\Http\Exception\{BadRequestException,
    ConflictException,
    ForbiddenException,
    HttpException,
    NotFoundException,
    UnauthorizedException,
    UnprocessableEntityException};
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A request handler throwing the exception.
 */
function throwing(Throwable $exception): RequestHandlerInterface
{
    return new readonly class ($exception) implements RequestHandlerInterface {
        public function __construct(private Throwable $exception) {}

        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            throw $this->exception;
        }
    };
}

test('has the status of its class, as status and code', function (HttpException $exception, int $status) {
    expect($exception->getStatus())->toBe($status)
        ->and($exception->getCode())->toBe($status)
        ->and($exception->getDetail())->toBe('detail')
        ->and($exception->getMessage())->toBe('detail')
        ->and($exception->getAdditionalData())->toBe(['key' => 'value'])
        ->and($exception->getTitle())->toBe('')
        ->and($exception->getType())->toBe('');
})->with([
    'bad request' => [new BadRequestException('detail', ['key' => 'value']), 400],
    'unauthorized' => [new UnauthorizedException('detail', ['key' => 'value']), 401],
    'forbidden' => [new ForbiddenException('detail', ['key' => 'value']), 403],
    'not found' => [new NotFoundException('detail', ['key' => 'value']), 404],
    'conflict' => [new ConflictException('detail', ['key' => 'value']), 409],
    'unprocessable entity' => [new UnprocessableEntityException('detail', ['key' => 'value']), 422],
    'any status' => [new HttpException(429, 'detail', ['key' => 'value']), 429],
]);

test('keeps the previous exception', function () {
    $previous = new RuntimeException('not found in the database');

    expect(new NotFoundException('The post does not exist', previous: $previous)->getPrevious())->toBe($previous);
});

test('refuses a status that is not an error', function (int $status) {
    new HttpException($status);
})->with([200, 302, 399, 600])->throws(InvalidArgumentException::class, 'An HTTP error status must be 4xx or 5xx');

test('becomes a Problem Details response with its status, detail and additional data, in production too', function () {
    $middleware = new ProblemDetailsMiddleware(new ProblemDetailsResponseFactory(new Psr17Factory(), isDebug: false));
    $exception = new UnprocessableEntityException('The post is invalid', ['errors' => ['title' => 'Required']]);

    $response = $middleware->process(
        new ServerRequest('POST', '/api/posts', ['Accept' => 'application/json']),
        throwing($exception)
    );

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getHeaderLine('Content-Type'))->toBe('application/problem+json')
        ->and(json_decode((string)$response->getBody(), true))->toMatchArray([
            'status' => 422,
            'title' => 'Unprocessable Entity',
            'detail' => 'The post is invalid',
            'errors' => ['title' => 'Required'],
        ]);
});

test('keeps the title and the type given to it', function () {
    $middleware = new ProblemDetailsMiddleware(new ProblemDetailsResponseFactory(new Psr17Factory()));
    $exception = new HttpException(402, 'Your plan is over', title: 'Payment needed', type: 'https://example.com/problems/plan');

    $body = json_decode(
        (string)$middleware->process(new ServerRequest('GET', '/api/posts', ['Accept' => 'application/json']), throwing($exception))->getBody(),
        true
    );

    expect($body['title'])->toBe('Payment needed')
        ->and($body['type'])->toBe('https://example.com/problems/plan');
});

test('is answered with its status by the ErrorHandler, outside the Problem Details', function () {
    $errorHandler = new ErrorHandler(new Psr17Factory(), new ErrorResponseGenerator(isDevelopmentMode: false));

    $response = $errorHandler->process(new ServerRequest('GET', '/posts/1'), throwing(new NotFoundException('No post 1')));

    expect($response->getStatusCode())->toBe(404)
        ->and((string)$response->getBody())->toBe('Not Found');
});
