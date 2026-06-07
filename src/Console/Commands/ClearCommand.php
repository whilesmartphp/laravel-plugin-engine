<?php

namespace WhileSmart\LaravelPluginEngine\Console\Commands;

class ClearCommand extends PluginCommand
{
    protected $signature = 'plugin:clear';

    protected $description = 'Remove the plugin cache file';

    public function handle()
    {
        $this->pluginManager->clearCachedPlugins();

        $this->info('Plugin cache cleared successfully.');

        return 0;
    }
}
