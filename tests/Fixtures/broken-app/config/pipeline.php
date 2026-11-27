<?php declare(strict_types=1);

use Mezzio\Router\Middleware\{DispatchMiddleware, RouteMiddleware};
use Piccolo\Application;

return static function (Application $app): void {
    $app->pipe(RouteMiddleware::class);
    $app->pipe(DispatchMiddleware::class);
};
