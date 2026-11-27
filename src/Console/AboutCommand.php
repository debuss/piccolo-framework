<?php declare(strict_types=1);

namespace Piccolo\Console;

use Composer\InstalledVersions;
use Piccolo\{Environment, Paths};
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * AboutCommand
 *
 * Describes the application and its environment: versions of Piccolo, environment, PSR-7 implementation, caches, PHP
 * settings having an effect on the performances, and the FrankenPHP worker configuration.
 */
#[AsCommand(name: 'about', description: 'Describes the application and its environment')]
class AboutCommand extends Command
{

    public function __construct(
        private readonly Environment $environment,
        private readonly Paths $paths,
        private readonly ContainerInterface $container
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cache = $this->paths->cache();
        $cacheFiles = is_dir($cache) ? array_values(array_filter(
            scandir($cache) ?: [],
            static fn (string $file): bool => $file[0] !== '.'
        )) : [];

        (new SymfonyStyle($input, $output))->definitionList(
            'Piccolo',
            ['Framework' => $this->version('debuss-a/piccolo-framework')],
            ['Container' => $this->version('debuss-a/piccolo-container')],
            new TableSeparator(),
            'Application',
            ['Environment' => $this->environment->value],
            ['Root directory' => $this->paths->root()],
            ['Timezone' => date_default_timezone_get()],
            ['PSR-7 implementation' => $this->container->get(ResponseFactoryInterface::class)::class],
            new TableSeparator(),
            'Caches',
            ['Directory' => $this->relative($cache)],
            ['Files' => $cacheFiles === [] ? 'none' : implode(', ', $cacheFiles)],
            ['Used' => $this->environment->isProduction() ? 'yes' : 'no, only in production'],
            new TableSeparator(),
            'PHP',
            ['Version' => PHP_VERSION],
            ['Architecture' => (PHP_INT_SIZE * 8) . ' bits'],
            ['Memory limit' => ini_get('memory_limit') ?: 'unknown'],
            ['OPcache' => $this->opcache()],
            ['Xdebug' => extension_loaded('xdebug') ? '<comment>enabled, it slows the application down</comment>' : 'disabled'],
            new TableSeparator(),
            'FrankenPHP',
            ['Worker' => getenv('FRANKENPHP_CONFIG') ?: 'not configured (FRANKENPHP_CONFIG)'],
            ['Max requests per worker' => getenv('MAX_REQUESTS') ?: 'unlimited (MAX_REQUESTS)'],
        );

        return Command::SUCCESS;
    }

    private function version(string $package): string
    {
        return class_exists(InstalledVersions::class) && InstalledVersions::isInstalled($package)
            ? (string)InstalledVersions::getPrettyVersion($package)
            : 'unknown';
    }

    /**
     * Its settings for the web server: the console usually runs without OPcache.
     */
    private function opcache(): string
    {
        if (!extension_loaded('Zend OPcache')) {
            return 'not installed';
        }

        if (!filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOL)) {
            return 'disabled';
        }

        return filter_var(ini_get('opcache.validate_timestamps'), FILTER_VALIDATE_BOOL)
            ? 'enabled, checking the files for changes (opcache.validate_timestamps)'
            : 'enabled';
    }

    private function relative(string $path): string
    {
        $root = $this->paths->root() . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
    }
}
