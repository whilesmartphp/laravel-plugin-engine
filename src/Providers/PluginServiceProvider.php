<?php

namespace WhileSmart\LaravelPluginEngine\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use WhileSmart\LaravelPluginEngine\Console\Commands\CacheCommand;
use WhileSmart\LaravelPluginEngine\Console\Commands\ClearCommand;
use WhileSmart\LaravelPluginEngine\Console\Commands\DisableCommand;
use WhileSmart\LaravelPluginEngine\Console\Commands\DiscoverCommand;
use WhileSmart\LaravelPluginEngine\Console\Commands\EnableCommand;
use WhileSmart\LaravelPluginEngine\Console\Commands\GenerateOpenApiDocsCommand;
use WhileSmart\LaravelPluginEngine\Console\Commands\InfoCommand;
use WhileSmart\LaravelPluginEngine\Console\Commands\InstallCommand;
use WhileSmart\LaravelPluginEngine\Console\Commands\ListCommand;
use WhileSmart\LaravelPluginEngine\Logging\LevelFilteringLogger;
use WhileSmart\LaravelPluginEngine\Services\ComposerRunner;
use WhileSmart\LaravelPluginEngine\Services\PluginManager;
use WhileSmart\LaravelPluginEngine\Services\SymfonyComposerRunner;

class PluginServiceProvider extends ServiceProvider
{
    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected $commands = [
        CacheCommand::class,
        ClearCommand::class,
        DisableCommand::class,
        DiscoverCommand::class,
        EnableCommand::class,
        InfoCommand::class,
        InstallCommand::class,
        ListCommand::class,
        GenerateOpenApiDocsCommand::class,
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

        $this->app->bind(ComposerRunner::class, SymfonyComposerRunner::class);
    }

    /**
     * Register the plugin manager instance.
     *
     * @return void
     */
    protected function registerPluginManager()
    {
        $this->app->singleton(PluginManager::class, function ($app) {
            return new PluginManager($app, null, $this->createLogger($app));
        });

        $this->app->alias(PluginManager::class, 'plugin.manager');
    }

    /**
     * Create the logger the plugin engine writes to, honoring the
     * configured channel and minimum level.
     */
    protected function createLogger(Application $app): LoggerInterface
    {
        $config = $app['config'];

        $channel = $app['log']->channel($config->get('plugins.log_channel'));

        return new LevelFilteringLogger($channel, $config->get('plugins.log_level') ?? 'warning');
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
    public function boot(): void
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
