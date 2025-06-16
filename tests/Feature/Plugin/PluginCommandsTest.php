<?php

namespace WhileSmart\LaravelPluginEngine\Tests\Feature\Plugin;

use Illuminate\Support\Facades\File;
use WhileSmart\LaravelPluginEngine\Tests\TestCase;

class PluginCommandsTest extends TestCase
{
    protected string $examplePluginId = 'example';

    protected function setUp(): void
    {
        parent::setUp();

        if (! File::isDirectory("{$this->pluginsPath}/{$this->examplePluginId}")) {
            $this->createTestPlugin($this->examplePluginId, [
                'name' => 'Example Plugin',
                'description' => 'An example plugin',
                'version' => '1.0.0',
                'enabled' => true,
                'provider' => 'ExamplePluginServiceProvider',
                'namespace' => 'ExamplePlugin',
            ]);
        }

        $providerPath = "{$this->pluginsPath}/{$this->examplePluginId}/src/ExampleServiceProvider.php";
        if (! file_exists($providerPath)) {
            file_put_contents($providerPath, '<?php'."\n\n".'namespace Trakli\Example;'."\n\n".'use Illuminate\Support\ServiceProvider;'."\n\n".'class ExampleServiceProvider extends ServiceProvider'."\n".'{'."\n    public function register() {}"."\n    public function boot() {}"."\n}");
        }
    }

    protected function tearDown(): void
    {
        $this->resetExamplePluginState();
        parent::tearDown();
    }

    protected function resetExamplePluginState(): void
    {
        $manifestPath = "{$this->pluginsPath}/{$this->examplePluginId}/plugin.json";
        if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true);
            if (($manifest['enabled'] ?? true) !== true) {
                $manifest['enabled'] = true;
                file_put_contents(
                    $manifestPath,
                    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                );
            }
        }
    }

    /** @test */
    public function it_lists_plugins()
    {
        $this->artisan('plugin:list')
            ->assertExitCode(0)
            ->expectsOutputToContain('Example Plugin');
    }

    /** @test */
    public function it_shows_plugin_info()
    {
        $this->artisan('plugin:info example')
            ->assertExitCode(0)
            ->expectsOutputToContain('Example Plugin');
    }

    /** @test */
    public function it_enables_plugins()
    {
        $this->artisan('plugin:disable example')
            ->assertExitCode(0);

        $this->artisan('plugin:enable example')
            ->assertExitCode(0);

        $this->artisan('plugin:info example')
            ->expectsOutputToContain('Enabled')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_disables_plugins()
    {
        $this->artisan('plugin:disable example')
            ->assertExitCode(0)
            ->expectsOutputToContain('disabled successfully');
    }

    /** @test */
    public function it_handles_nonexistent_plugin()
    {
        $this->artisan('plugin:info nonexistent')
            ->expectsOutputToContain('not found')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_suggests_similar_plugin_names()
    {
        $this->artisan('plugin:info examp')
            ->expectsOutputToContain('Did you mean one of these?')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_handles_invalid_plugin_manifest()
    {
        $pluginPath = $this->createTestPlugin('invalid_plugin', [
            'name' => 'Invalid Plugin',
            'invalid' => 'json',
        ]);

        File::put(
            "{$pluginPath}/plugin.json",
            '{"name": "Invalid Plugin", "invalid": "json"'
        );

        $this->artisan('plugin:info invalid_plugin')
            ->assertExitCode(1)
            ->expectsOutputToContain("Plugin 'invalid_plugin' has errors: Invalid JSON in plugin manifest: Syntax error");
    }

    /** @test */
    public function it_handles_missing_id_in_manifest()
    {
        $pluginId = 'missing_id_plugin';
        $pluginPath = "{$this->pluginsPath}/{$pluginId}";

        File::ensureDirectoryExists($pluginPath);

        File::put(
            "{$pluginPath}/plugin.json",
            json_encode([
                'name' => 'Plugin with Missing ID',
                'version' => '1.0.0',
            ], JSON_PRETTY_PRINT)
        );

        $this->artisan('plugin:info missing_id_plugin')
            ->assertExitCode(1)
            ->expectsOutputToContain("Plugin 'missing_id_plugin' has errors: Plugin manifest missing required 'id' field");
    }
}
