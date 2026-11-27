<?php declare(strict_types=1);

namespace Piccolo\Console;

use Piccolo\Kernel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * RoutesListCommand
 *
 * Lists the routes registered by `config/routes.php` (from the handler attributes and the manual ones), in their order
 * of registration, with the middlewares of each route followed by its handler.
 */
#[AsCommand(name: 'routes:list', description: 'Lists the routes of the application')]
class RoutesListCommand extends Command
{

    public function __construct(
        private readonly Kernel $kernel
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = [];
        foreach ($this->kernel->http()->getRoutes() as $route) {
            $rows[] = [
                $route->getName(),
                implode('|', $route->getAllowedMethods() ?? ['ANY']),
                $route->getPath(),
                MiddlewareInspector::describe($route->getMiddleware()),
            ];
        }

        (new SymfonyStyle($input, $output))->table(['Name', 'Methods', 'Path', 'Middlewares and handler'], $rows);

        return Command::SUCCESS;
    }
}
