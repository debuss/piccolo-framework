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
 * @see https://docs.mezzio.dev/mezzio/v3/features/template/interface/
 */
class TemplateRendererServiceProvider implements ServiceProvider
{

    public function register(ContainerBuilder $builder): void
    {
        $builder->singleton(TemplateRendererInterface::class, static function (): TemplateRendererInterface {
            $plates = new Engine();
            $plates->addFolder('app', storage_path('templates'));

            return new PlatesRenderer($plates);
        });
    }
}
