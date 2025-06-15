<?php

namespace Trakli\PluginEngine\Services;

use Composer\Autoload\ClassLoader;
use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class PluginManager
{
    protected Application $app;

    protected string $pluginsPath;

    protected array $plugins = [];

    protected ClassLoader $classLoader;

    protected $output;

    public function __construct(Application $app, $output = null)
    {
        $this->app = $app;
        $this->pluginsPath = base_path('plugins');
        $this->classLoader = require base_path('vendor/autoload.php');
        $this->output = $output ?? new OutputStyle(new ArrayInput([]), new NullOutput);
    }

    /**
     * Find a plugin by its ID (case-insensitive)
     */
    public function findPlugin(string $pluginId): ?array
    {
        $pluginId = trim($pluginId);
        if (empty($pluginId)) {
            return null;
        }

        $plugins = $this->discover();

        return $plugins->first(fn ($p) => strtolower($p['id']) === strtolower($pluginId));
    }

    public function discover(): Collection
    {
        if (! empty($this->plugins)) {
            Log::debug('Returning cached plugins');

            return collect($this->plugins);
        }

        Log::debug('Starting plugin discovery', ['path' => $this->pluginsPath]);

        if (! is_dir($this->pluginsPath)) {
            Log::warning("Plugins directory not found: {$this->pluginsPath}");

            return collect();
        }

        $plugins = [];
        $directories = new \DirectoryIterator($this->pluginsPath);
        $foundDirs = [];

        foreach ($directories as $directory) {
            $foundDirs[] = $directory->getBasename();

            if (! $directory->isDir() || $directory->isDot()) {
                Log::debug('Skipping non-directory or dot file', ['path' => $directory->getPathname()]);
                continue;
            }

            $pluginPath = $directory->getPathname();
            $manifestPath = $pluginPath.'/plugin.json';
            Log::debug('Checking plugin directory', ['path' => $pluginPath]);

            if (! file_exists($manifestPath)) {
                Log::debug("Plugin manifest not found in: {$pluginPath}");
                continue;
            }

            Log::debug('Found plugin manifest', ['path' => $manifestPath]);
            $plugin = $this->loadPlugin($pluginPath);

            if ($plugin) {
                $expectedDirName = $plugin['id'] ?? null;
                $actualDirName = $directory->getBasename();

                if ($expectedDirName !== $actualDirName) {
                    Log::warning(sprintf(
                        'Plugin directory name "%s" does not match plugin ID "%s"',
                        $actualDirName,
                        $expectedDirName
                    ));
                }

                $plugins[] = $plugin;
            }
        }

        $this->plugins = $plugins;

        return collect($plugins);
    }

    protected function loadPlugin(string $pluginPath): ?array
    {
        $manifestPath = $pluginPath.'/plugin.json';
        
        try {
            $manifest = json_decode(File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
            
            if (! isset($manifest['id'])) {
                Log::error("Plugin manifest missing required 'id' field", ['path' => $manifestPath]);
                return null;
            }

            $plugin = array_merge([
                'path' => $pluginPath,
                'enabled' => $manifest['enabled'] ?? false,
            ], $manifest);

            return $plugin;
        } catch (\JsonException $e) {
            Log::error("Failed to parse plugin manifest: " . $e->getMessage(), [
                'path' => $manifestPath,
                'exception' => $e
            ]);
            return null;
        }
    }

    public function registerPlugins()
    {
        $plugins = $this->discover();
        
        foreach ($plugins as $plugin) {
            if ($plugin['enabled'] && isset($plugin['provider'])) {
                // Add plugin's directory to the autoloader
                $pluginNamespace = $plugin['namespace'] ?? '';
                $pluginSrcPath = $plugin['path'] . '/src';
                
                if (is_dir($pluginSrcPath) && !empty($pluginNamespace)) {
                    $this->classLoader->addPsr4($pluginNamespace . '\\', $pluginSrcPath);
                }
                
                // Register the service provider
                $this->app->register($plugin['provider']);
            }
        }
    }
}
