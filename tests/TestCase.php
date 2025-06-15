<?php

namespace Trakli\PluginEngine\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Trakli\PluginEngine\Providers\PluginServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            PluginServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Set up test environment
        $app['config']->set('app.key', 'base64:yWa1By9jVUlwUb4iAArLf1SKJ6td8tnttBmGnf4tFTk=');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        
        // Set up plugin paths
        $app['config']->set('plugins.path', base_path('plugins'));
    }
    
    /**
     * Reset the example plugin's state.
     *
     * @return void
     */
    protected function resetExamplePluginState(): void
    {
        $manifestPath = base_path('plugins/example/plugin.json');
        
        if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true);
            $manifest['enabled'] = true; // Ensure the plugin is enabled by default for tests
            file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }
}
