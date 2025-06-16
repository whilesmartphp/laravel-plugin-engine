<?php

namespace WhileSmart\LaravelPluginEngine\Console\Commands;

class EnableCommand extends PluginCommand
{
    protected $signature = 'plugin:enable
        {id : The ID of the plugin to enable}';

    protected $description = 'Enable a plugin';

    public function handle()
    {
        try {
            $plugin = $this->resolvePlugin($this->argument('id'));
            $pluginId = $plugin['id'];
            $pluginName = $plugin['name'] ?? $pluginId;

            if (($plugin['enabled'] ?? false)) {
                $this->info("Plugin [{$pluginName}] is already enabled.");

                return 0;
            }

            // Update the plugin's enabled status
            $manifestPath = $plugin['path'].'/plugin.json';
            $manifest = json_decode(file_get_contents($manifestPath), true);
            $manifest['enabled'] = true;

            file_put_contents(
                $manifestPath,
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            $this->info("Plugin [{$pluginName}] enabled successfully.");

            // Clear caches to ensure the plugin is immediately available
            $this->call('config:clear');
            $this->call('route:clear');

            return 0;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }
}
