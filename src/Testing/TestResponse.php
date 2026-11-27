<?php declare(strict_types=1);

namespace Piccolo\Testing;

use JsonException;
use LogicException;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

/**
 * The response to a request of the TestClient, with assertions for PHPUnit and Pest:
 *
 *  $client->get('/api/v1/posts/1')
 *      ->assertOk()
 *      ->assertHeader('Content-Type', 'application/json')
 *      ->assertJson(['id' => 1]);
 *
 * Or with the expectations of Pest: expect($response->status())->toBe(200).
 */
final readonly class TestResponse
{

    public function __construct(
        public ResponseInterface $response
    ) {}

    public function status(): int
    {
        return $this->response->getStatusCode();
    }

    /**
     * The values of the header, separated by commas, or an empty string when it is missing.
     */
    public function header(string $name): string
    {
        return $this->response->getHeaderLine($name);
    }

    public function body(): string
    {
        return (string)$this->response->getBody();
    }

    /**
     * The decoded JSON body, objects as arrays.
     *
     * @throws JsonException When the body is not JSON.
     */
    public function json(): mixed
    {
        return json_decode($this->body(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function assertStatus(int $status): self
    {
        self::assert()::assertSame($status, $this->status(), sprintf(
            'Expected the status %d, got %d with the body: %s',
            $status,
            $this->status(),
            mb_strimwidth($this->body(), 0, 1000, '...')
        ));

        return $this;
    }

    public function assertOk(): self
    {
        return $this->assertStatus(200);
    }

    /**
     * The header is present, and has the value when one is given.
     */
    public function assertHeader(string $name, ?string $value = null): self
    {
        self::assert()::assertTrue($this->response->hasHeader($name), sprintf('The response has no %s header', $name));

        if ($value !== null) {
            self::assert()::assertSame($value, $this->header($name), sprintf('Unexpected value of the %s header', $name));
        }

        return $this;
    }

    public function assertHeaderMissing(string $name): self
    {
        self::assert()::assertFalse($this->response->hasHeader($name), sprintf('The response has a %s header', $name));

        return $this;
    }

    /**
     * The JSON body contains the data: the other keys of the body are ignored, at any depth.
     *
     * @param array<mixed> $data
     *
     * @throws JsonException When the body is not JSON.
     */
    public function assertJson(array $data): self
    {
        $json = $this->json();

        self::assert()::assertIsArray($json, 'The JSON body is not an object nor an array');
        self::assert()::assertSame(
            $data,
            self::intersect($data, is_array($json) ? $json : []),
            'The JSON body does not contain the data'
        );

        return $this;
    }

    public function assertBodyContains(string $text): self
    {
        self::assert()::assertStringContainsString($text, $this->body());

        return $this;
    }

    /**
     * The values of the actual data for the keys of the expected data, recursively, in the order of the expected data.
     *
     * @param array<mixed> $expected
     * @param array<mixed> $actual
     * @return array<mixed>
     */
    private static function intersect(array $expected, array $actual): array
    {
        $intersection = [];

        foreach ($expected as $key => $value) {
            if (!array_key_exists($key, $actual)) {
                continue;
            }

            $intersection[$key] = is_array($value) && is_array($actual[$key])
                ? self::intersect($value, $actual[$key])
                : $actual[$key];
        }

        return $intersection;
    }

    /**
     * @return class-string<Assert>
     *
     * @throws LogicException When PHPUnit (or Pest) is not installed.
     */
    private static function assert(): string
    {
        return class_exists(Assert::class)
            ? Assert::class
            : throw new LogicException('The assertions of the TestResponse need PHPUnit (or Pest)');
    }
}
