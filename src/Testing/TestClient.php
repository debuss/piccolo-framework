<?php declare(strict_types=1);

namespace Piccolo\Testing;

use JsonException;
use JsonSerializable;
use Piccolo\{Application, Kernel};
use Piccolo\Container\Container;
use Psr\Http\Message\{ServerRequestFactoryInterface, ServerRequestInterface, StreamFactoryInterface};

/**
 * Sends requests to the application as its HTTP server would, for the end-to-end tests:
 *
 *  $client = new TestClient(dirname(__DIR__));
 *  $client->get('/api/ping')->assertOk()->assertJson(['ack' => true]);
 *  $client->post('/api/v1/posts', ['title' => 'Hello'])->assertStatus(201);
 *
 * The application is booted once per client (configuration, container, pipeline and routes), then each request is
 * handled in its own scope, as in a FrankenPHP worker: the singletons are shared by the requests of a client.
 *
 * The requests are created with the PSR-17 factories of the container, so that the tests do not depend on the PSR-7
 * implementation. Their query string is parsed into the query parameters.
 */
final class TestClient
{

    private readonly Kernel $kernel;

    private readonly Application $application;

    /**
     * @param string $root The root directory of the application.
     */
    public function __construct(string $root)
    {
        $this->kernel = Kernel::boot($root);
        $this->application = $this->kernel->http();
    }

    public function kernel(): Kernel
    {
        return $this->kernel;
    }

    public function container(): Container
    {
        return $this->kernel->container();
    }

    /**
     * @param array<string, string|string[]> $headers
     */
    public function get(string $uri, array $headers = []): TestResponse
    {
        return $this->request('GET', $uri, $headers);
    }

    /**
     * @param array<string, string|string[]> $headers
     */
    public function head(string $uri, array $headers = []): TestResponse
    {
        return $this->request('HEAD', $uri, $headers);
    }

    /**
     * @param array<string, string|string[]> $headers
     */
    public function options(string $uri, array $headers = []): TestResponse
    {
        return $this->request('OPTIONS', $uri, $headers);
    }

    /**
     * @param array<string, string|string[]> $headers
     */
    public function delete(string $uri, array $headers = []): TestResponse
    {
        return $this->request('DELETE', $uri, $headers);
    }

    /**
     * A request with the data as JSON body.
     *
     * @param array<mixed>|JsonSerializable $data
     * @param array<string, string|string[]> $headers
     *
     * @throws JsonException When the data cannot be encoded.
     */
    public function post(string $uri, array|JsonSerializable $data = [], array $headers = []): TestResponse
    {
        return $this->json('POST', $uri, $data, $headers);
    }

    /**
     * @param array<mixed>|JsonSerializable $data
     * @param array<string, string|string[]> $headers
     *
     * @throws JsonException When the data cannot be encoded.
     */
    public function put(string $uri, array|JsonSerializable $data = [], array $headers = []): TestResponse
    {
        return $this->json('PUT', $uri, $data, $headers);
    }

    /**
     * @param array<mixed>|JsonSerializable $data
     * @param array<string, string|string[]> $headers
     *
     * @throws JsonException When the data cannot be encoded.
     */
    public function patch(string $uri, array|JsonSerializable $data = [], array $headers = []): TestResponse
    {
        return $this->json('PATCH', $uri, $data, $headers);
    }

    /**
     * A request with the data as JSON body (`Content-Type` and `Accept: application/json`), parsed by the
     * BodyParamsMiddleware when the route has it.
     *
     * @param array<mixed>|JsonSerializable $data
     * @param array<string, string|string[]> $headers
     *
     * @throws JsonException When the data cannot be encoded.
     */
    public function json(string $method, string $uri, array|JsonSerializable $data, array $headers = []): TestResponse
    {
        return $this->request(
            $method,
            $uri,
            ['Content-Type' => 'application/json', 'Accept' => 'application/json', ...$headers],
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * A request with the data as form body, given as parsed body too, as PHP does for the forms ($_POST).
     *
     * @param array<string, mixed> $data
     * @param array<string, string|string[]> $headers
     */
    public function form(string $method, string $uri, array $data, array $headers = []): TestResponse
    {
        $request = $this->createRequest(
            $method,
            $uri,
            ['Content-Type' => 'application/x-www-form-urlencoded', ...$headers],
            http_build_query($data)
        );

        return $this->handle($request->withParsedBody($data));
    }

    /**
     * @param array<string, string|string[]> $headers
     */
    public function request(string $method, string $uri, array $headers = [], string $body = ''): TestResponse
    {
        return $this->handle($this->createRequest($method, $uri, $headers, $body));
    }

    /**
     * A request to complete before handling it (cookies, attributes, uploaded files, ...).
     *
     * @param array<string, string|string[]> $headers
     */
    public function createRequest(string $method, string $uri, array $headers = [], string $body = ''): ServerRequestInterface
    {
        $container = $this->kernel->container();
        $method = strtoupper($method);

        $request = $container->get(ServerRequestFactoryInterface::class)->createServerRequest($method, $uri, [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        parse_str($request->getUri()->getQuery(), $query);
        $request = $request->withQueryParams($query);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($body !== '') {
            $request = $request->withBody($container->get(StreamFactoryInterface::class)->createStream($body));
        }

        return $request;
    }

    public function handle(ServerRequestInterface $request): TestResponse
    {
        return new TestResponse($this->application->handle($request));
    }
}
