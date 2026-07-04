<?php

namespace WhileSmart\LaravelPluginEngine\Tests\Feature\Plugin;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use WhileSmart\LaravelPluginEngine\Services\ComposerRunner;
use WhileSmart\LaravelPluginEngine\Tests\Stubs\FakeComposerRunner;
use WhileSmart\LaravelPluginEngine\Tests\TestCase;

class InstallCommandTest extends TestCase
{
    private FakeComposerRunner $composer;

    private string $sourceParent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->composer = new FakeComposerRunner;
        $this->app->instance(ComposerRunner::class, $this->composer);

        $this->sourceParent = sys_get_temp_dir().'/engine-install-src-'.Str::random(8);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->sourceParent)) {
            File::deleteDirectory($this->sourceParent);
        }

        parent::tearDown();
    }

    /** @test */
    public function it_installs_the_plugins_declared_dependencies_from_a_local_path()
    {
        $source = $this->makeSourcePlugin('acme-bank', [
            'require' => [
                'php' => '^8.1',
                'acme/widget' => '^1.0',
            ],
        ]);

        $this->artisan('plugin:install', ['package' => $source])
            ->assertExitCode(0);

        $this->assertDirectoryExists("{$this->pluginsPath}/acme-bank");
        $this->assertTrue(
            $this->composer->ran('composer', 'acme/widget:^1.0'),
            'Expected the plugin dependency to be installed via Composer.'
        );
        $this->assertFalse(
            $this->composer->ran('php:^8.1'),
            'Platform constraints must not be passed to Composer as packages.'
        );
    }

    /** @test */
    public function it_installs_the_plugin_without_composer_when_there_is_no_composer_json()
    {
        $source = $this->makeSourcePlugin('acme-plain', null);

        $this->artisan('plugin:install', ['package' => $source])
            ->assertExitCode(0);

        $this->assertDirectoryExists("{$this->pluginsPath}/acme-plain");
        $this->assertSame([], $this->composer->commands);
    }

    /** @test */
    public function it_does_not_call_composer_when_require_holds_only_platform_packages()
    {
        $source = $this->makeSourcePlugin('acme-platform', [
            'require' => ['php' => '^8.1', 'ext-json' => '*'],
        ]);

        $this->artisan('plugin:install', ['package' => $source])
            ->assertExitCode(0);

        $this->assertSame([], $this->composer->commands);
    }

    /**
     * Build a plugin source directory (plugin.json, a provider stub, and an
     * optional composer.json) the install command can copy into place.
     * Returns the path to copy from.
     */
    private function makeSourcePlugin(string $id, ?array $composer): string
    {
        $source = "{$this->sourceParent}/{$id}";

        File::ensureDirectoryExists("{$source}/src");

        File::put("{$source}/plugin.json", json_encode([
            'id' => $id,
            'name' => 'Test Plugin '.ucfirst($id),
            'version' => '1.0.0',
            'namespace' => 'WhileSmart\\AcmePlugin',
            'provider' => 'WhileSmart\\AcmePlugin\\AcmeServiceProvider',
            'enabled' => false,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        File::put(
            "{$source}/src/AcmeServiceProvider.php",
            "<?php\n\nnamespace WhileSmart\\AcmePlugin;\n\nuse Illuminate\\Support\\ServiceProvider;\n\nclass AcmeServiceProvider extends ServiceProvider\n{\n    public function register() {}\n\n    public function boot() {}\n}\n"
        );

        if ($composer !== null) {
            File::put("{$source}/composer.json", json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return $source;
    }
}
