<?php declare(strict_types=1);

namespace Piccolo\ServiceProvider;

use League\Plates\Engine;
use Mezzio\Plates\PlatesRenderer;
use Mezzio\Template\TemplateRendererInterface;
use Piccolo\Container\{ContainerBuilder, ServiceProvider};

/**
 * Template Renderer Service Provider
 *
 * The templates of the `app` namespace are in `storage/templates` (`app::home-page`).
 *
 * The renderer is scoped: one per request, created only by the requests rendering a template. The default parameters
 * added during a request (addDefaultParam(), e.g. the current user) are kept by the Plates engine: with a single
 * renderer, a worker would render them in the next requests too. Hence a singleton cannot depend on the renderer.
 *
 * @see https://docs.mezzio.dev/mezzio/v3/features/template/interface/
 */
class TemplateRendererServiceProvider implements ServiceProvider
{

    public function register(ContainerBuilder $builder): void
    {
        $builder->scoped(TemplateRendererInterface::class, static function (): TemplateRendererInterface {
            $plates = new Engine();
            $plates->addFolder('app', storage_path('templates'));

            return new PlatesRenderer($plates);
        });
    }
}
