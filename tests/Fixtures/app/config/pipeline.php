<?php declare(strict_types=1);

use Laminas\Stratigility\Handler\NotFoundHandler;
use Laminas\Stratigility\Middleware\ErrorHandler;
use Mezzio\Router\Middleware\{DispatchMiddleware, MethodNotAllowedMiddleware, RouteMiddleware};
use Piccolo\Application;
use Piccolo\Middleware\RequestIdMiddleware;
use Psr\Container\ContainerInterface;

return static function (Application $app, ContainerInterface $container): void {
    $app->pipe(RequestIdMiddleware::class);
    $app->pipe(ErrorHandler::class);
    $app->pipe(RouteMiddleware::class);
    $app->pipe(MethodNotAllowedMiddleware::class);
    $app->pipe(DispatchMiddleware::class);
    $app->pipe(NotFoundHandler::class);
};
