<?php

namespace WhileSmart\LaravelPluginEngine\Tests\Feature\Plugin;

use Illuminate\Support\Facades\File;
use WhileSmart\LaravelPluginEngine\Services\PluginManager;
use WhileSmart\LaravelPluginEngine\Tests\TestCase;

class PluginCacheTest extends TestCase
{
    /** @test */
    public function it_caches_plugins_via_the_cache_command()
    {
        $this->createTestPlugin('example');

        $this->artisan('plugin:cache')
            ->assertExitCode(0)
            ->expectsOutputToContain('Plugins cached successfully');

        $manager = $this->app->make(PluginManager::class);

        $this->assertFileExists($manager->getCachedPluginsPath());

        $cached = require $manager->getCachedPluginsPath();
        $this->assertSame('example', $cached[0]['id']);
    }

    /** @test */
    public function it_discovers_from_the_cache_file_without_scanning()
    {
        $this->createTestPlugin('example');

        $manager = new PluginManager($this->app);
        $manager->cachePlugins();

        // Remove the real plugin; a cached discovery must not notice
        File::deleteDirectory("{$this->pluginsPath}/example");

        $fresh = new PluginManager($this->app);
        $plugins = $fresh->discover();

        $this->assertCount(1, $plugins);
        $this->assertSame('example', $plugins->first()['id']);
    }

    /** @test */
    public function it_bypasses_the_cache_file_on_a_fresh_discovery()
    {
        $this->createTestPlugin('example');

        $manager = new PluginManager($this->app);
        $manager->cachePlugins();

        File::deleteDirectory("{$this->pluginsPath}/example");

        $this->assertTrue($manager->discover(true)->isEmpty());
    }

    /** @test */
    public function it_clears_the_cache_via_the_clear_command()
    {
        $this->createTestPlugin('example');

        $manager = $this->app->make(PluginManager::class);
        $manager->cachePlugins();

        $this->artisan('plugin:clear')
            ->assertExitCode(0)
            ->expectsOutputToContain('Plugin cache cleared successfully');

        $this->assertFileDoesNotExist($manager->getCachedPluginsPath());
    }

    /** @test */
    public function it_refreshes_an_existing_cache_when_a_plugin_is_disabled()
    {
        $this->createTestPlugin('example', ['enabled' => true]);

        $manager = $this->app->make(PluginManager::class);
        $manager->cachePlugins();

        $this->artisan('plugin:disable example')->assertExitCode(0);

        $cached = require $manager->getCachedPluginsPath();
        $this->assertFalse($cached[0]['enabled']);
    }

    /** @test */
    public function it_does_not_create_a_cache_when_none_exists_on_disable()
    {
        $this->createTestPlugin('example', ['enabled' => true]);

        $manager = $this->app->make(PluginManager::class);

        $this->artisan('plugin:disable example')->assertExitCode(0);

        $this->assertFileDoesNotExist($manager->getCachedPluginsPath());
    }
}
