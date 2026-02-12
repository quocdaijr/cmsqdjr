<?php

namespace Modules\Core\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\ResourceRegistrar;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Events\BuildSidebarEvent;
use Modules\Core\Exceptions\CoreHandler;
use Modules\Core\Listeners\BuildCoreSidebarListener;
use Modules\Core\Supports\CustomResourceRegistrar;
use Modules\Core\Traits\RegisterDataTrait;
// use Maatwebsite\Sidebar\SidebarServiceProvider as PackageSidebarServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    use RegisterDataTrait;
    /**
     * @var string $moduleName
     */
    protected $moduleName = 'Core';

    /**
     * @var string $moduleNameLower
     */
    protected $moduleNameLower = 'core';

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerTranslations();
        $this->registerMultiConfig(['config']);
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
        Blade::directive('datetime', function ($expression) {
            return "<?php echo ($expression)->format('m/d/Y H:i'); ?>";
        });
        Paginator::defaultView('core::partials.pagination');
        Blade::componentNamespace('Modules\\Core\\Views\\Components\\Forms\\Fields', 'field');
        Blade::componentNamespace('Modules\\Core\\Views\\Components\\Modals', 'modal');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(ResourceRegistrar::class, function ($app) {
            return new CustomResourceRegistrar($app[Router::class]);
        });
        $this->app->register(RouteServiceProvider::class);
        // $this->app->register(PackageSidebarServiceProvider::class); // Removed: Package not Laravel 12 compatible
        // $this->app->register(SidebarServiceProvider::class); // Removed: Depends on maatwebsite/laravel-sidebar
        $this->app->singleton(ExceptionHandler::class, CoreHandler::class);

        $this->app['events']->listen(
            BuildSidebarEvent::class,
            BuildCoreSidebarListener::class
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/' . $this->moduleNameLower);

        $sourcePath = module_path($this->moduleName, 'Resources/views');

        $this->publishes([
            $sourcePath => $viewPath
        ], ['views', $this->moduleNameLower . '-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->moduleNameLower);
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/' . $this->moduleNameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
        } else {
            $this->loadTranslationsFrom(module_path($this->moduleName, 'Resources/lang'), $this->moduleNameLower);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (\Config::get('view.paths') as $path) {
            if (is_dir($path . '/modules/' . $this->moduleNameLower)) {
                $paths[] = $path . '/modules/' . $this->moduleNameLower;
            }
        }
        return $paths;
    }
}
