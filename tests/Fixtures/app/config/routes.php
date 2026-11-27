<?php declare(strict_types=1);

use Piccolo\Application;
use Piccolo\Middleware\HttpCacheMiddleware;
use Psr\Container\ContainerInterface;
use Mezzio\Helper\BodyParams\BodyParamsMiddleware;
use Tests\Fixtures\{CountingHandler, EchoHandler, NoopRequestHandler};

return static function (Application $app, ContainerInterface $container): void {
    $app->get('/count', CountingHandler::class, 'count');
    $app->route('/cached', [HttpCacheMiddleware::class, NoopRequestHandler::class], ['GET'], 'cached');
    $app->get('/posts/{id:\d+}', NoopRequestHandler::class, 'post');
    $app->any('/echo', [BodyParamsMiddleware::class, EchoHandler::class], 'echo');
};
