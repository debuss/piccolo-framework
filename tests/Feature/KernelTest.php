<?php declare(strict_types=1);

use Borsch\Config\Config;
use Nyholm\Psr7\ServerRequest;
use Laminas\Stratigility\Middleware\ErrorHandler;
use Mezzio\Cors\Middleware\CorsMiddleware;
use Mezzio\Helper\BodyParams\BodyParamsMiddleware;
use Mezzio\ProblemDetails\ProblemDetailsMiddleware;
use Mezzio\Router\Middleware\{DispatchMiddleware, ImplicitHeadMiddleware, ImplicitOptionsMiddleware, MethodNotAllowedMiddleware, RouteMiddleware};
use Mezzio\Template\TemplateRendererInterface;
use Piccolo\{Application, Environment, Kernel, Paths};
use Piccolo\Console\RoutesListCommand;
use Piccolo\Http\ServerRequestErrorResponseGenerator;
use Piccolo\Runtime\{RunnerInterface, SapiRunner};
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Fixtures\NoopCommand;

beforeEach(function () {
    $this->timezone = date_default_timezone_get();
    $this->displayErrors = ini_get('display_errors');
});

afterEach(function () {
    date_default_timezone_set($this->timezone);
    ini_set('display_errors', $this->displayErrors);
    Paths::setCurrent(new Paths(dirname(__DIR__, 2)));
});

test('boots the paths, the configuration, the environment and the PHP runtime settings', function () {
    $kernel = Kernel::boot(fixtureApp());

    expect($kernel->paths->root)->toBe(realpath(fixtureApp()))
        ->and(config_path('routes.php'))->toBe(realpath(fixtureApp()) . '/config/routes.php')
        ->and($kernel->config->get('FIXTURE'))->toBe('loaded')
        ->and($kernel->environment)->toBe(Environment::Development)
        ->and(date_default_timezone_get())->toBe('Europe/Paris')
        ->and(ini_get('display_errors'))->toBe('1');
});

test('registers the configuration, the environment, the paths, then the definitions of config/container.php', function () {
    $container = Kernel::boot(fixtureApp())->container();

    expect($container->get(Config::class)->get('FIXTURE'))->toBe('loaded')
        ->and($container->get(Paths::class))->toBe(Paths::current())
        ->and($container->get('fixture.environment'))->toBe(Environment::Development)
        ->and($container->get(Monolog\Logger::class)->getName())->toBe('fixture');
});

test('resolves every service of the framework', function (string $id) {
    $scope = Kernel::boot(fixtureApp())->container()->createScope();

    expect($scope->get($id))->toBeObject();
})->with([
    Application::class,
    ErrorHandler::class,
    CorsMiddleware::class,
    ProblemDetailsMiddleware::class,
    BodyParamsMiddleware::class,
    RouteMiddleware::class,
    ImplicitHeadMiddleware::class,
    ImplicitOptionsMiddleware::class,
    MethodNotAllowedMiddleware::class,
    DispatchMiddleware::class,
    TemplateRendererInterface::class,
    ServerRequestErrorResponseGenerator::class,
    RoutesListCommand::class,
]);

test('creates the stateless middlewares of the framework once, for all the requests', function (string $id) {
    $container = Kernel::boot(fixtureApp())->container();

    expect($container->createScope()->get($id))->toBe($container->createScope()->get($id));
})->with([
    Piccolo\Middleware\RequestIdMiddleware::class,
    Laminas\Stratigility\Middleware\OriginalMessages::class,
    Piccolo\Middleware\ApiAcceptHeaderMiddleware::class,
    Piccolo\Middleware\HttpCacheMiddleware::class,
    BodyParamsMiddleware::class,
    Laminas\Stratigility\Handler\NotFoundHandler::class,
]);

test('runs the requests with the SAPI runner outside a FrankenPHP worker', function () {
    expect(Kernel::boot(fixtureApp())->container()->get(RunnerInterface::class))->toBeInstanceOf(SapiRunner::class);
});

test('gives the application with the pipeline and the routes, loaded once', function () {
    $kernel = Kernel::boot(fixtureApp());

    $application = $kernel->http();

    expect($kernel->http())->toBe($application)
        ->and($application->getRoutes())->toHaveCount(3)
        ->and($application->handle(new ServerRequest('GET', '/count'))->getStatusCode())->toBe(200)
        ->and($application->handle(new ServerRequest('POST', '/count'))->getStatusCode())->toBe(405)
        ->and($application->handle(new ServerRequest('GET', '/missing'))->getStatusCode())->toBe(404);
});

test('gives the console with the commands of the framework and of config/commands.php, resolved in a scope', function () {
    $console = Kernel::boot(fixtureApp())->console();
    $console->setAutoExit(false);

    expect($console->has('about'))->toBeTrue()
        ->and($console->has('cache:clear'))->toBeTrue()
        ->and($console->has('cache:warmup'))->toBeTrue()
        ->and($console->has('container:check'))->toBeTrue()
        ->and($console->has('container:list'))->toBeTrue()
        ->and($console->has('routes:list'))->toBeTrue()
        ->and($console->has('routes:match'))->toBeTrue()
        // Its scoped dependency could not be resolved without a scope
        ->and($console->get('fixture:noop'))->toBeInstanceOf(NoopCommand::class)
        ->and($console->run(new ArrayInput(['command' => 'fixture:noop']), new BufferedOutput()))->toBe(0);
});

test('resolves itself from its container, for the commands needing the HTTP application', function () {
    $kernel = Kernel::boot(fixtureApp());

    expect($kernel->container()->get(Kernel::class))->toBe($kernel);
});
