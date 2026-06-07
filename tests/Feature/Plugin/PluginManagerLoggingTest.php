<?php

namespace WhileSmart\LaravelPluginEngine\Tests\Feature\Plugin;

use Illuminate\Support\Facades\File;
use Psr\Log\LogLevel;
use WhileSmart\LaravelPluginEngine\Services\PluginManager;
use WhileSmart\LaravelPluginEngine\Tests\Stubs\Logging\SpyLogger;
use WhileSmart\LaravelPluginEngine\Tests\TestCase;

class PluginManagerLoggingTest extends TestCase
{
    /** @test */
    public function it_logs_a_missing_plugins_directory_at_debug_not_warning()
    {
        File::deleteDirectory($this->pluginsPath);

        $spy = new SpyLogger;
        $manager = new PluginManager($this->app, null, $spy);

        $plugins = $manager->discover();

        $this->assertTrue($plugins->isEmpty());
        $this->assertNotContains(LogLevel::WARNING, $spy->levels());
        $this->assertContains(LogLevel::DEBUG, $spy->levels());
    }

    /** @test */
    public function it_logs_a_single_summary_line_for_a_discovery_scan()
    {
        $this->createTestPlugin('alpha');
        $this->createTestPlugin('beta');

        $spy = new SpyLogger;
        $manager = new PluginManager($this->app, null, $spy);

        $manager->discover();

        $debugRecords = array_filter($spy->records, fn ($r) => $r['level'] === LogLevel::DEBUG);

        $this->assertCount(1, $debugRecords);
        $this->assertSame(2, array_values($debugRecords)[0]['context']['found']);
    }

    /** @test */
    public function it_still_warns_when_directory_name_does_not_match_plugin_id()
    {
        $this->createTestPlugin('mismatched', ['id' => 'other-id']);

        $spy = new SpyLogger;
        $manager = new PluginManager($this->app, null, $spy);

        $manager->discover();

        $this->assertContains(LogLevel::WARNING, $spy->levels());
    }
}
