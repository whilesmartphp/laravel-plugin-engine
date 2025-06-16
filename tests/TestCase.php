<?php

namespace Trakli\PluginEngine\Tests;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Trakli\PluginEngine\Providers\PluginServiceProvider;
use Trakli\PluginEngine\Tests\Stubs\Models\User;

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

        $this->pluginsPath = base_path('plugins');

        if (! File::isDirectory($this->pluginsPath)) {
            File::makeDirectory($this->pluginsPath, 0755, true);
        }

        $this->app['config']->set('plugins.path', $this->pluginsPath);
        $this->app->instance('path.plugins', $this->pluginsPath);

        Factory::guessFactoryNamesUsing(function (string $modelName) {
            return 'Trakli\\PluginEngine\\Tests\\Stubs\\Factories\\'.class_basename($modelName).'Factory';
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Clean up the testing environment before the next test.
     */
    protected function tearDown(): void
    {
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
            SanctumServiceProvider::class,
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
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('auth.providers.users.model', User::class);

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
            'name' => 'Test Plugin '.ucfirst($pluginId),
            'description' => 'Test plugin description',
            'version' => '1.0.0',
            'namespace' => 'Trakli\\'.ucfirst($pluginId).'Plugin',
            'provider' => 'Trakli\\'.ucfirst($pluginId).'Plugin\\'.ucfirst($pluginId).'ServiceProvider',
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
