<?php declare(strict_types=1);

use Mezzio\Template\TemplateRendererInterface;
use Piccolo\Container\ContainerBuilder;
use Piccolo\Container\Exception\ScopeException;
use Piccolo\ServiceProvider\TemplateRendererServiceProvider;
use Piccolo\Paths;

beforeEach(function () {
    $this->templates = sys_get_temp_dir() . '/piccolo-templates-' . bin2hex(random_bytes(4));
    mkdir($this->templates . '/storage/templates', recursive: true);
    file_put_contents($this->templates . '/storage/templates/hello.php', 'Hello <?= $this->e($name ?? "nobody") ?>');
    Paths::setCurrent(new Paths($this->templates));
});

afterEach(function () {
    unlink($this->templates . '/storage/templates/hello.php');
    rmdir($this->templates . '/storage/templates');
    rmdir($this->templates . '/storage');
    rmdir($this->templates);
    Paths::setCurrent(new Paths(dirname(__DIR__, 3)));
});

test('gives a renderer per request, so that the default parameters of a request do not reach the next ones', function () {
    $container = new ContainerBuilder()->addProvider(new TemplateRendererServiceProvider())->build();

    $first = $container->createScope();
    $renderer = $first->get(TemplateRendererInterface::class);
    $renderer->addDefaultParam(TemplateRendererInterface::TEMPLATE_ALL, 'name', 'Alice');

    $second = $container->createScope();

    expect($first->get(TemplateRendererInterface::class))->toBe($renderer)
        ->and($renderer->render('app::hello'))->toBe('Hello Alice')
        ->and($second->get(TemplateRendererInterface::class)->render('app::hello'))->toBe('Hello nobody');
});

test('cannot be resolved outside a request, nor by a singleton', function () {
    $container = new ContainerBuilder()->addProvider(new TemplateRendererServiceProvider())->build();

    $container->get(TemplateRendererInterface::class);
})->throws(ScopeException::class);
