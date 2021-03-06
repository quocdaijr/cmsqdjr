<?php

namespace Modules\Post\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Events\BuildSidebarEvent;
use Modules\Core\Traits\RegisterDataTrait;
use Modules\Post\Console\BuildPostElasticsearchCommand;
use Modules\Post\Entities\Post;
use Modules\Post\Indexes\Post as PostIndex;
use Modules\Post\Listeners\BuildPostSidebarListener;
use Modules\Post\Repositories\Elasticsearch\PostElasticsearchRepository;
use Modules\Post\Repositories\Eloquent\PostRepository;
use Modules\Post\Repositories\Interfaces\PostElasticsearchRepositoryInterface;
use Modules\Post\Repositories\Interfaces\PostRepositoryInterface;

class PostServiceProvider extends ServiceProvider
{
    use RegisterDataTrait;

    /**
     * @var string $moduleName
     */
    protected $moduleName = 'Post';

    /**
     * @var string $moduleNameLower
     */
    protected $moduleNameLower = 'post';

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerTranslations();
        $this->registerMultiConfig(['config', 'permissions']);
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);

        $this->app->bind(PostRepositoryInterface::class, function () {
            return new PostRepository(new Post());
        });

        $this->app->bind(PostElasticsearchRepositoryInterface::class, function () {
            return new PostElasticsearchRepository(new PostIndex());
        });
        $this->app['events']->listen(
            BuildSidebarEvent::class,
            BuildPostSidebarListener::class
        );
        $this->commands([
            BuildPostElasticsearchCommand::class
        ]);
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
