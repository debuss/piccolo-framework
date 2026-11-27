<?php declare(strict_types=1);

namespace Piccolo\Console;

use Mezzio\Router\RouterInterface;
use Piccolo\Kernel;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * RoutesMatchCommand
 *
 * Tells which route of config/routes.php matches a request, with its middlewares, its handler and the parameters taken
 * from the path; or why none does (404, or 405 with the methods allowed on the path).
 *
 *  bin/console routes:match GET /api/v1/posts/1
 */
#[AsCommand(name: 'routes:match', description: 'Tells which route matches a request')]
class RoutesMatchCommand extends Command
{

    public function __construct(
        private readonly Kernel $kernel
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('method', InputArgument::REQUIRED, 'The method of the request, e.g. GET')
            ->addArgument('uri', InputArgument::REQUIRED, 'The path of the request, e.g. /api/v1/posts/1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $method = strtoupper((string)$input->getArgument('method'));
        $uri = (string)$input->getArgument('uri');

        $this->kernel->http();
        $container = $this->kernel->container();

        $request = $container->get(ServerRequestFactoryInterface::class)->createServerRequest($method, $uri);
        $result = $container->get(RouterInterface::class)->match($request);
        $route = $result->getMatchedRoute();

        if ($result->isSuccess() && $route !== false) {
            $parameters = $result->getMatchedParams();

            $io->success(sprintf('%s %s matches the route "%s".', $method, $uri, $route->getName()));
            $io->definitionList(
                ['Route' => $route->getName()],
                ['Path' => $route->getPath()],
                ['Methods' => implode('|', $route->getAllowedMethods() ?? ['ANY'])],
                ['Middlewares and handler' => MiddlewareInspector::describe($route->getMiddleware())],
                ['Parameters' => $parameters === [] ? 'none' : implode(', ', array_map(
                    static fn (string $name, mixed $value): string => sprintf('%s = %s', $name, $value),
                    array_keys($parameters),
                    $parameters
                ))]
            );

            return Command::SUCCESS;
        }

        if ($result->isMethodFailure()) {
            $io->error(sprintf(
                '%s %s: 405 Method Not Allowed, the path only allows %s.',
                $method,
                $uri,
                implode(', ', $result->getAllowedMethods() ?? [])
            ));

            if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                $io->note(sprintf(
                    'With ImplicitHeadMiddleware and ImplicitOptionsMiddleware in the pipeline, the application answers %s requests on the path anyway.',
                    $method
                ));
            }

            return Command::FAILURE;
        }

        $io->error(sprintf('%s %s: 404 Not Found, no route matches the path.', $method, $uri));

        return Command::FAILURE;
    }
}
