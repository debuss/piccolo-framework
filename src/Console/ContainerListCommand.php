<?php declare(strict_types=1);

namespace Piccolo\Console;

use Closure;
use Piccolo\Container\{Container, Definition};
use Piccolo\Paths;
use ReflectionFunction;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * ContainerListCommand
 *
 * Lists the services registered by the providers of the framework and config/container.php, with their lifetime and
 * how they are created, then the aliases and the types having inflectors.
 */
#[AsCommand(name: 'container:list', description: 'Lists the services of the container, with their lifetime')]
class ContainerListCommand extends Command
{

    public function __construct(
        private readonly Container $container,
        private readonly Paths $paths
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'filter',
            InputArgument::OPTIONAL,
            'Only the services, aliases and types containing it (case insensitive)'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filter = $input->getArgument('filter');
        $filter = is_string($filter) ? $filter : '';
        $matches = static fn (string ...$values): bool => $filter === ''
            || array_any($values, static fn (string $value): bool => stripos($value, $filter) !== false);

        $definitions = array_filter($this->container->definitions(), static fn (Definition $definition) => $matches($definition->id));
        ksort($definitions);

        $io->section('Services');
        $io->table(
            ['Service', 'Lifetime', 'Created from'],
            array_map(
                fn (Definition $definition): array => [
                    $definition->id,
                    strtolower($definition->lifetime->name),
                    $this->createdFrom($definition)
                ],
                array_values($definitions)
            )
        );

        $aliases = array_filter($this->container->aliases(), $matches(...), ARRAY_FILTER_USE_BOTH);
        ksort($aliases);

        if ($aliases !== []) {
            $io->section('Aliases');
            $io->table(
                ['Alias', 'Service'],
                array_map(null, array_keys($aliases), array_values($aliases))
            );
        }

        $types = array_filter($this->container->inflectedTypes(), $matches(...));

        if ($types !== []) {
            $io->section('Inflectors');
            $io->listing($types);
        }

        $io->note('The classes that are not registered (handlers, middlewares, ...) are autowired as transient services.');

        return Command::SUCCESS;
    }

    private function createdFrom(Definition $definition): string
    {
        if ($definition->hasInstance) {
            return sprintf('instance of %s', get_debug_type($definition->instance));
        }

        if ($definition->concrete instanceof Closure) {
            $factory = new ReflectionFunction($definition->concrete);
            $file = (string)$factory->getFileName();
            $root = $this->paths->root() . DIRECTORY_SEPARATOR;

            return sprintf(
                'factory (%s:%d)',
                str_starts_with($file, $root) ? substr($file, strlen($root)) : $file,
                $factory->getStartLine()
            );
        }

        return $definition->concrete ?? 'autowired';
    }
}
