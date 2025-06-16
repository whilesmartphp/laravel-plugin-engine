<?php

namespace WhileSmart\LaravelPluginEngine\Providers;

use Illuminate\Support\ServiceProvider;
use WhileSmart\LaravelPluginEngine\Services\PluginManager;

class PluginServiceProvider extends ServiceProvider
{
    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected $commands = [
        \WhileSmart\LaravelPluginEngine\Console\Commands\DisableCommand::class,
        \WhileSmart\LaravelPluginEngine\Console\Commands\DiscoverCommand::class,
        \WhileSmart\LaravelPluginEngine\Console\Commands\EnableCommand::class,
        \WhileSmart\LaravelPluginEngine\Console\Commands\InfoCommand::class,
        \WhileSmart\LaravelPluginEngine\Console\Commands\InstallCommand::class,
        \WhileSmart\LaravelPluginEngine\Console\Commands\ListCommand::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/plugins.php', 'plugins'
        );

        $this->registerPluginManager();
        $this->registerCommands();
    }

    /**
     * Register the plugin manager instance.
     *
     * @return void
     */
    protected function registerPluginManager()
    {
        $this->app->singleton(PluginManager::class, function ($app) {
            return new PluginManager($app);
        });

        $this->app->alias(PluginManager::class, 'plugin.manager');
    }

    /**
     * Register the console commands.
     *
     * @return void
     */
    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands($this->commands);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        $this->publishConfig();
        $this->registerPlugins();
    }

    /**
     * Publish the config file.
     *
     * @return void
     */
    protected function publishConfig()
    {
        $this->publishes([
            __DIR__.'/../../config/plugins.php' => config_path('plugins.php'),
        ], 'config');
    }

    /**
     * Register all enabled plugins.
     *
     * @return void
     */
    protected function registerPlugins()
    {
        $this->app->booted(function () {
            $this->app->make(PluginManager::class)->registerPlugins();
        });
    }
}
