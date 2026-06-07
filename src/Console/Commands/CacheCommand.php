<?php

namespace WhileSmart\LaravelPluginEngine\Console\Commands;

class CacheCommand extends PluginCommand
{
    protected $signature = 'plugin:cache';

    protected $description = 'Create a cache file for faster plugin discovery';

    public function handle()
    {
        $this->pluginManager->clearCachedPlugins();
        $path = $this->pluginManager->cachePlugins();

        $this->info("Plugins cached successfully at [{$path}].");

        return 0;
    }
}
