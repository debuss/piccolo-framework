<?php declare(strict_types=1);

use Borsch\Config\Config;

return new Config([
    'APP_ENV' => 'development',
    'LOG_STREAM' => 'php://memory',
    'TIMEZONE' => 'Europe/Paris',
    'FIXTURE' => 'loaded',
]);
