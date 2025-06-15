<?php

namespace Trakli\PluginEngine\Console\Commands;

class DiscoverCommand extends PluginCommand
{
    protected $signature = 'plugin:discover';

    protected $description = 'Discover and register all available plugins';

    public function handle()
    {
        $this->info('Discovering plugins...');
        
        $plugins = $this->pluginManager->discover();
        
        if ($plugins->isEmpty()) {
            $this->warn('No plugins found.');
            return 0;
        }

        $this->info("Discovered {$plugins->count()} " . str('plugin')->plural($plugins->count()) . ".");
        
        // Trigger registration of plugins
        $this->pluginManager->registerPlugins();
        
        $this->info('Plugins discovered and registered successfully.');
        
        return 0;
    }
}
