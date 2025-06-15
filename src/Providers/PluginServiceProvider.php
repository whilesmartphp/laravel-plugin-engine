<?php

namespace Trakli\PluginEngine\Providers;

use Illuminate\Support\ServiceProvider;
use Trakli\PluginEngine\Services\PluginManager;
use Trakli\PluginEngine\Console\Commands\DisableCommand;
use Trakli\PluginEngine\Console\Commands\DiscoverCommand;
use Trakli\PluginEngine\Console\Commands\EnableCommand;
use Trakli\PluginEngine\Console\Commands\InfoCommand;
use Trakli\PluginEngine\Console\Commands\InstallCommand;
use Trakli\PluginEngine\Console\Commands\ListCommand;

class PluginServiceProvider extends ServiceProvider
{
    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected $commands = [
        DisableCommand::class,
        DiscoverCommand::class,
        EnableCommand::class,
        InfoCommand::class,
        InstallCommand::class,
        ListCommand::class,
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
