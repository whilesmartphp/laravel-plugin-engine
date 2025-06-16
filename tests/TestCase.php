<?php

namespace Trakli\PluginEngine\Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Trakli\PluginEngine\Providers\PluginServiceProvider;
use Trakli\PluginEngine\Tests\Stubs\User;
use Illuminate\Contracts\Foundation\Application;

/**
 * @property Application $app
 */
class TestCase extends OrchestraTestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * The base path to the plugins directory.
     */
    protected string $pluginsPath;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Set up the plugins path
        $this->pluginsPath = base_path('plugins');
        
        // Ensure the plugins directory exists
        if (!File::isDirectory($this->pluginsPath)) {
            File::makeDirectory($this->pluginsPath, 0755, true);
        }

        // Set the plugins path in the application
        $this->app['config']->set('plugins.path', $this->pluginsPath);
        $this->app->instance('path.plugins', $this->pluginsPath);
    }

    /**
     * Clean up the testing environment before the next test.
     */
    protected function tearDown(): void
    {
        // Clean up any test plugins
        if (File::isDirectory($this->pluginsPath)) {
            File::cleanDirectory($this->pluginsPath);
        }

        parent::tearDown();
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            PluginServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    /**
     * Create a test plugin directory structure.
     */
    protected function createTestPlugin(string $pluginId, array $manifest = []): string
    {
        $pluginPath = "{$this->pluginsPath}/{$pluginId}";
        
        File::ensureDirectoryExists("{$pluginPath}/src");
        
        $defaultManifest = [
            'id' => $pluginId,
            'name' => 'Test Plugin ' . ucfirst($pluginId),
            'description' => 'Test plugin description',
            'version' => '1.0.0',
            'namespace' => 'Trakli\\' . ucfirst($pluginId) . 'Plugin',
            'provider' => 'Trakli\\' . ucfirst($pluginId) . 'Plugin\\' . ucfirst($pluginId) . 'ServiceProvider',
            'enabled' => true,
        ];
        
        $manifest = array_merge($defaultManifest, $manifest);
        
        File::put(
            "{$pluginPath}/plugin.json",
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        
        return $pluginPath;
    }
}