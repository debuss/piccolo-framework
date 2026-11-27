<?php declare(strict_types=1);

use Piccolo\Paths;
use Piccolo\Testing\{TestClient, TestResponse};
use PHPUnit\Framework\AssertionFailedError;

afterEach(fn () => Paths::setCurrent(new Paths(dirname(__DIR__, 3))));

test('sends a request to the application, with its headers and its query parameters', function () {
    $response = new TestClient(fixtureApp())->get('/echo?page=2&tags[]=php', ['X-Test' => 'value']);

    expect($response)->toBeInstanceOf(TestResponse::class)
        ->and($response->status())->toBe(200)
        ->and($response->header('X-Echo'))->toBe('yes')
        ->and($response->json())->toMatchArray([
            'method' => 'GET',
            'path' => '/echo',
            'query' => ['page' => '2', 'tags' => ['php']],
            'header' => 'value',
            'body' => '',
        ]);
});

test('sends the data as JSON body, parsed by the BodyParamsMiddleware', function (string $method) {
    $json = new TestClient(fixtureApp())->{strtolower($method)}('/echo', ['title' => 'Café'])->json();

    expect($json['method'])->toBe($method)
        ->and($json['body'])->toBe('{"title":"Café"}')
        ->and($json['parsed'])->toBe(['title' => 'Café']);
})->with(['POST', 'PUT', 'PATCH']);

test('sends the data of a form, as body and parsed body', function () {
    $json = new TestClient(fixtureApp())->form('post', '/echo', ['name' => 'Alice', 'age' => 30])->json();

    expect($json['method'])->toBe('POST')
        ->and($json['body'])->toBe('name=Alice&age=30')
        ->and($json['parsed'])->toBe(['name' => 'Alice', 'age' => 30]);
});

test('handles each request in its own scope, the singletons being shared', function () {
    $client = new TestClient(fixtureApp());

    $first = $client->get('/count')->json();
    $second = $client->get('/count')->json();

    expect($first['count'])->toBe(2)
        ->and($second['count'])->toBe(2)
        ->and($client->container()->get(Psr\Log\LoggerInterface::class))
        ->toBe($client->container()->get(Psr\Log\LoggerInterface::class));
});

test('handles a request completed by the test', function () {
    $client = new TestClient(fixtureApp());
    $request = $client->createRequest('DELETE', '/echo', ['X-Test' => 'created'])->withCookieParams(['session' => 'abc']);

    expect($client->handle($request)->json())->toMatchArray(['method' => 'DELETE', 'header' => 'created']);
});

test('asserts the status, the headers and the JSON body', function () {
    new TestClient(fixtureApp())->get('/echo?page=2', ['X-Test' => 'value'])
        ->assertOk()
        ->assertHeader('X-Echo')
        ->assertHeader('X-Echo', 'yes')
        ->assertHeaderMissing('X-Missing')
        ->assertJson(['query' => ['page' => '2'], 'header' => 'value'])
        ->assertBodyContains('"method":"GET"');
});

test('fails with the status and the body of the response', function () {
    new TestClient(fixtureApp())->get('/missing')->assertOk();
})->throws(AssertionFailedError::class, 'Expected the status 200, got 404 with the body:');

test('fails when the JSON body does not contain the data', function () {
    new TestClient(fixtureApp())->get('/echo')->assertJson(['method' => 'POST']);
})->throws(AssertionFailedError::class, 'The JSON body does not contain the data');
