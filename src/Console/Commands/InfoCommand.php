<?php

namespace WhileSmart\LaravelPluginEngine\Console\Commands;

class InfoCommand extends PluginCommand
{
    protected $signature = 'plugin:info
        {id : The ID of the plugin to show information for}';

    protected $description = 'Show detailed information about a plugin';

    public function handle()
    {
        try {
            $plugin = $this->resolvePlugin($this->argument('id'));
            $pluginId = $plugin['id'];
            $pluginName = $plugin['name'] ?? $pluginId;

            $this->info("Plugin: {$pluginName}");
            $this->line(str_repeat('-', 50));

            $info = [
                'ID' => $pluginId,
                'Name' => $pluginName,
                'Description' => $plugin['description'] ?? 'No description',
                'Version' => $plugin['version'] ?? '1.0.0',
                'Enabled' => $plugin['enabled'] ? 'Yes' : 'No',
                'Path' => $plugin['path'] ?? 'N/A',
                'Provider' => $plugin['provider'] ?? 'N/A',
                'Namespace' => $plugin['namespace'] ?? 'N/A',
            ];

            $this->table(
                ['Property', 'Value'],
                array_map(
                    fn ($key, $value) => [$key, $value],
                    array_keys($info),
                    array_values($info)
                )
            );

            // Show requirements if they exist
            if (isset($plugin['requires']) && is_array($plugin['requires'])) {
                $this->line("\n<comment>Requirements:</comment>");
                $this->table(
                    ['Package', 'Version'],
                    array_map(
                        fn ($pkg, $version) => [$pkg, $version],
                        array_keys($plugin['requires']),
                        array_values($plugin['requires'])
                    )
                );
            }

            return 0;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }
}
