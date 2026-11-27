<?php declare(strict_types=1);

use Piccolo\Container\ContainerBuilder;
use Tests\Fixtures\{CountingHandler, RequestCounter};

/*
 * An application with services that cannot be resolved, for container:check.
 */
return static function (ContainerBuilder $builder): void {
    $builder->scoped(RequestCounter::class);

    // A singleton depending on a scoped service
    $builder->singleton(CountingHandler::class);

    // A class that does not exist
    $builder->transient('missing', 'Tests\Fixtures\MissingService');
};
