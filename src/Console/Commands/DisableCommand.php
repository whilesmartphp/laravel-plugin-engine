<?php

namespace WhileSmart\LaravelPluginEngine\Console\Commands;

class DisableCommand extends PluginCommand
{
    protected $signature = 'plugin:disable
        {id : The ID of the plugin to disable}';

    protected $description = 'Disable a plugin';

    public function handle()
    {
        try {
            $plugin = $this->resolvePlugin($this->argument('id'));
            $pluginId = $plugin['id'];
            $pluginName = $plugin['name'] ?? $pluginId;

            if (! ($plugin['enabled'] ?? false)) {
                $this->info("Plugin [{$pluginName}] is already disabled.");

                return 0;
            }

            // Update the plugin's enabled status
            $manifestPath = $plugin['path'].'/plugin.json';
            $manifest = json_decode(file_get_contents($manifestPath), true);
            $manifest['enabled'] = false;

            file_put_contents(
                $manifestPath,
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            $this->info("Plugin [{$pluginName}] disabled successfully.");

            // Clear caches to ensure the plugin is immediately unavailable
            $this->call('config:clear');
            $this->call('route:clear');

            return 0;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }
}
