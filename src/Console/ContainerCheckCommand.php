<?php declare(strict_types=1);

namespace Piccolo\Console;

use Laminas\Stratigility\MiddlewarePipe;
use Piccolo\Kernel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * ContainerCheckCommand
 *
 * Resolves, in a scope as a request would, every registered service and alias, and the middlewares and handlers of the
 * pipeline (config/pipeline.php) and of the routes (config/routes.php). The errors otherwise only seen on the first
 * request using the service (a parameter that cannot be resolved, a scoped service in a singleton, a circular
 * dependency, a missing class) are found before a deployment, e.g. in the CI.
 *
 * The services are really created: a factory opening a connection opens it. Such a service can be left out with
 * --exclude where it cannot be created (e.g. in a CI without database).
 */
#[AsCommand(
    name: 'container:check',
    description: 'Checks that the services, middlewares and handlers can be resolved from the container'
)]
class ContainerCheckCommand extends Command
{

    public function __construct(
        private readonly Kernel $kernel
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'exclude',
            'x',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'A service not to resolve (e.g. one needing a database not reachable here)'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $container = $this->kernel->container();
        $application = $this->kernel->http();

        $services = [
            ...array_keys($container->definitions()),
            ...array_keys($container->aliases()),
            ...MiddlewareInspector::services($container->get(MiddlewarePipe::class)),
        ];
        foreach ($application->getRoutes() as $route) {
            array_push($services, ...MiddlewareInspector::services($route->getMiddleware()));
        }

        $services = array_values(array_diff(array_unique($services), $input->getOption('exclude')));

        $scope = $container->createScope();
        $failures = [];

        foreach ($services as $id) {
            try {
                $scope->get($id);
                $io->writeln(sprintf(' <info>✔</info> %s', $id), OutputInterface::VERBOSITY_VERBOSE);
            } catch (Throwable $exception) {
                $failures[$id] = $exception->getMessage();
            }
        }

        try {
            $scope->dispose();
        } catch (Throwable $exception) {
            $failures['(disposing the scope)'] = $exception->getMessage();
        }

        if ($failures === []) {
            $io->success(sprintf('The %d services, middlewares and handlers can be resolved.', count($services)));

            return Command::SUCCESS;
        }

        foreach ($failures as $id => $message) {
            $io->writeln(sprintf(' <error>✘</error> <comment>%s</comment>', $id));
            $io->writeln(sprintf('   %s', $message));
            $io->newLine();
        }

        $io->error(sprintf('%d of the %d services cannot be resolved.', count($failures), count($services)));

        return Command::FAILURE;
    }
}
