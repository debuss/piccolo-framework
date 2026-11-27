<?php declare(strict_types=1);

use Piccolo\Application;
use Tests\Fixtures\UnresolvableHandler;

return static function (Application $app): void {
    // A handler that cannot be created
    $app->get('/broken', UnresolvableHandler::class, 'broken');
};
