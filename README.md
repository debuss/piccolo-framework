# Piccolo Framework

The framework of the [Piccolo skeleton](https://github.com/debuss/piccolo): a PSR-15 application built on Laminas
Stratigility and Mezzio components (without `mezzio/mezzio`), with
[a scoped container](https://github.com/debuss/piccolo-container), attribute-based routing, and support for the
FrankenPHP worker mode.

```bash
composer create-project debuss-a/piccolo my-app   # the skeleton, which requires this package
```

## What it provides

| Namespace | Content |
|---|---|
| `Piccolo\Kernel` | Boots the application: paths, configuration, environment, PHP settings, container |
| `Piccolo\Application` | The PSR-15 application: middleware pipeline, routes, one container scope per request |
| `Piccolo\Runtime` | `SapiRunner` (PHP-FPM, built-in server, ...) and `FrankenPhpWorkerRunner`, chosen automatically |
| `Piccolo\ServiceProvider` | HTTP factories (Nyholm PSR-7), logger (Monolog), router (FastRoute), error handler, Problem Details, CORS, templates (Plates) |
| `Piccolo\Middleware` | `RequestIdMiddleware`, `HttpCacheMiddleware`, `ApiAcceptHeaderMiddleware`, `LazyLoadingMiddleware` |
| `Piccolo\Http` | `Handler` (base class of the request handlers: `html()`, `text()`, `xml()`, `json()`, `redirect()`, `empty()`), error logging, request identifier |
| `Piccolo\Console` | `cache:clear`, `cache:warmup`, `routes:list` |
| `Piccolo\Paths` | The directories of the application, and the helpers `app_path()`, `config_path()`, `storage_path()`, ... |

## Application layout

The kernel expects this layout, from the root directory given to `Kernel::boot()`:

```text
config/
  configuration.php   optional: returns a Borsch\Config\Config (default: .env, then the environment variables)
  container.php       optional: static function (ContainerBuilder $builder, Config $config, Environment $environment): void
  pipeline.php        static function (Application $app, ContainerInterface $container): void
  routes.php          static function (Application $app, ContainerInterface $container): void
  commands.php        optional: returns ['name' => CommandClass::class, ...]
storage/
  cache/  logs/  templates/
```

```php
// public/index.php
Kernel::boot(dirname(__DIR__))->http()->run();

// bin/console
exit(Kernel::boot(dirname(__DIR__))->console()->run());
```

The definitions of `config/container.php` are registered after the providers of the framework, so they can replace
them (another logger, another PSR-7 implementation with `new HttpFactoryServiceProvider(...)`, ...).

## Lifetimes and worker mode

| Lifetime | Framework services |
|---|---|
| Singleton | configuration, environment, paths, PSR-17 factories, logger, router and routing middlewares, pipeline, application, error handler, Problem Details, CORS, template renderer |
| Scoped | none: for the services of the application with a per-request state (unit of work, current user, ...) |
| Transient | the classes that are not registered: request handlers, middlewares, ... |

Each request is handled in its own scope: the middlewares and handlers piped or routed as class names are resolved from
it, and the scope is disposed once the response is created.

When the script is run by a FrankenPHP worker (`frankenphp_handle_request()` exists and `$_SERVER['FRANKENPHP_WORKER']`
is set), the kernel is booted once, and the requests are handled in a loop. After each request, the singletons holding a
state are reset (Monolog buffers and processors, the request identifier, ...) and the garbage collector runs. The worker
stops after `MAX_REQUESTS` requests (unlimited by default) so that FrankenPHP restarts it.

Rule of thumb in a worker: **never keep a request state in a singleton**: make the service scoped, or reset it (implement
`Piccolo\Container\Resettable`, or register a hook with `$builder->onReset()`).

## Tests

```bash
composer test
```
