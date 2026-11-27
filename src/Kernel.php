<?php declare(strict_types=1);

namespace Piccolo;

use Borsch\Config\{Aggregator, Config};
use Borsch\Config\Provider\DotEnvProvider;
use Piccolo\Console\{AboutCommand,
    CacheClearCommand,
    CacheWarmupCommand,
    ContainerCheckCommand,
    ContainerListCommand,
    RoutesListCommand,
    RoutesMatchCommand};
use Piccolo\Container\{Container, ContainerBuilder, ServiceProvider};
use Piccolo\ServiceProvider\{ApplicationServiceProvider,
    CorsServiceProvider,
    ErrorHandlerServiceProvider,
    FastRouteRouterServiceProvider,
    HttpFactoryServiceProvider,
    LoggerServiceProvider,
    ProblemDetailsServiceProvider,
    TemplateRendererServiceProvider};
use LogicException;
use Symfony\Component\Console\Application as Console;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;

/**
 * Kernel
 *
 * Boots the application from its root directory, the same way for the HTTP and console entry points:
 *
 *  1. the paths of the application (used by the helpers app_path(), config_path(), ...);
 *  2. the configuration, from config/configuration.php (by default, the .env file and the environment variables);
 *  3. the environment (APP_ENV), which drives the PHP runtime settings: errors are never displayed in production, they
 *     are handled by the ErrorHandler middleware and logged instead. The timezone (TIMEZONE) applies to the whole
 *     application;
 *  4. the container: the service providers of the framework, then the definitions of config/container.php, which can
 *     replace them.
 *
 *  public/index.php: Kernel::boot(dirname(__DIR__))->http()->run();
 *  bin/console:      exit(Kernel::boot(dirname(__DIR__))->console()->run());
 *
 * In a FrankenPHP worker, the kernel is booted once: the container, the pipeline and the routes are kept from a request
 * to the next one.
 */
final class Kernel
{

    private bool $httpLoaded = false;

    private function __construct(
        public readonly Paths $paths,
        public readonly Config $config,
        public readonly Environment $environment,
        private readonly Container $container
    ) {}

    public static function boot(string $root): self
    {
        error_reporting(E_ALL);

        // Hide errors until the environment is known, they are displayed again in development
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');

        $paths = Paths::setCurrent(new Paths($root));
        $config = self::configuration($paths);
        $environment = Environment::fromConfig($config);

        ini_set('display_errors', $environment->isProduction() ? '0' : '1');
        ini_set('display_startup_errors', $environment->isProduction() ? '0' : '1');

        date_default_timezone_set(ConfigValue::string($config, 'TIMEZONE', 'UTC'));

        // The kernel itself, for the commands needing the HTTP application (see http())
        /** @var self|null $kernel */
        $kernel = null;

        $builder = new ContainerBuilder()
            ->instance(Paths::class, $paths)
            ->instance(Config::class, $config)
            ->instance(Environment::class, $environment)
            ->singleton(self::class, static function () use (&$kernel): self {
                return $kernel ?? throw new LogicException('The kernel cannot be resolved while it boots');
            });

        foreach (self::providers() as $provider) {
            $builder->addProvider($provider);
        }

        $definitions = $paths->config('container.php');
        if (is_file($definitions)) {
            ConfigFile::closure($definitions)($builder, $config, $environment);
        }

        return $kernel = new self($paths, $config, $environment, $builder->build());
    }

    /**
     * The service providers of the framework, registered before the definitions of the application.
     *
     * @return ServiceProvider[]
     */
    public static function providers(): array
    {
        return [
            new HttpFactoryServiceProvider(),
            new LoggerServiceProvider(),
            new ApplicationServiceProvider(),
            new FastRouteRouterServiceProvider(),
            new ErrorHandlerServiceProvider(),
            new TemplateRendererServiceProvider(),
            new ProblemDetailsServiceProvider(),
            new CorsServiceProvider(),
        ];
    }

    public function container(): Container
    {
        return $this->container;
    }

    /**
     * The application, with the middleware pipeline of config/pipeline.php and the routes of config/routes.php (loaded
     * once).
     */
    public function http(): Application
    {
        $application = $this->container->get(Application::class);

        if (!$this->httpLoaded) {
            $this->httpLoaded = true;

            foreach (['pipeline.php', 'routes.php'] as $file) {
                ConfigFile::closure($this->paths->config($file))($application, $this->container);
            }
        }

        return $application;
    }

    /**
     * The console, with the commands of the framework and the ones of config/commands.php, resolved from the container
     * with their dependencies only when they are run (or listed).
     *
     * The commands are resolved from a scope, as a request would be, disposed when the process ends.
     */
    public function console(string $name = 'Piccolo'): Console
    {
        $commands = [
            'about' => AboutCommand::class,
            'cache:clear' => CacheClearCommand::class,
            'cache:warmup' => CacheWarmupCommand::class,
            'container:check' => ContainerCheckCommand::class,
            'container:list' => ContainerListCommand::class,
            'routes:list' => RoutesListCommand::class,
            'routes:match' => RoutesMatchCommand::class,
        ];

        $file = $this->paths->config('commands.php');
        if (is_file($file)) {
            $commands = [...$commands, ...ConfigFile::commands($file)];
        }

        $scope = $this->container->createScope();
        register_shutdown_function($scope->dispose(...));

        $console = new Console($name);
        $console->setCommandLoader(new ContainerCommandLoader($scope, $commands));

        return $console;
    }

    private static function configuration(Paths $paths): Config
    {
        $file = $paths->config('configuration.php');
        if (is_file($file)) {
            return ConfigFile::config($file);
        }

        // Merged in order (the last one wins): the .env file, optional, then the real environment variables
        return new Aggregator([
            new DotEnvProvider($paths->root('.env')),
            getenv()
        ])->getMergedConfig();
    }
}
