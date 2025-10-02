<?php

namespace WhileSmart\LaravelPluginEngine\Services;

use Composer\Autoload\ClassLoader;
use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use WhileSmart\LaravelPluginEngine\Traits\Loggable;

class PluginManager
{
    use Loggable;

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
     * Check if a plugin is enabled
     */
    public function isPluginEnabled(string $pluginId): bool
    {
        $plugin = $this->findPlugin($pluginId);

        return $plugin && ($plugin['enabled'] ?? false) === true;
    }

    /**
     * Enable a plugin by ID
     */
    public function enablePlugin(string $pluginId): bool
    {
        return $this->setPluginEnabled($pluginId, true);
    }

    /**
     * Disable a plugin by ID
     */
    public function disablePlugin(string $pluginId): bool
    {
        return $this->setPluginEnabled($pluginId, false);
    }

    /**
     * Set the enabled state of a plugin
     */
    protected function setPluginEnabled(string $pluginId, bool $enabled): bool
    {
        $plugin = $this->findPlugin($pluginId);

        if (! $plugin) {
            return false;
        }

        $manifestPath = $plugin['path'].'/plugin.json';

        try {
            $manifest = json_decode(File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
            $manifest['enabled'] = $enabled;

            File::put(
                $manifestPath,
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );

            // Update the cached plugin data
            $this->plugins = [];
            $this->discover();

            return true;
        } catch (\Exception $e) {
            $this->error('Failed to update plugin manifest: '.$e->getMessage(), [
                'plugin' => $pluginId,
                'path' => $manifestPath,
                'exception' => $e,
            ]);

            return false;
        }
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

        return $plugins->first(function ($p) use ($pluginId) {
            // Check if plugin has an ID and it matches (case-insensitive)
            $pluginIdLower = strtolower($pluginId);
            $foundId = isset($p['id']) && strtolower($p['id']) === $pluginIdLower;

            // Also check if the directory name matches (for invalid plugins without ID)
            $dirName = basename($p['path'] ?? '');
            $foundDir = strtolower($dirName) === $pluginIdLower;

            return $foundId || $foundDir;
        });
    }

    public function discover(): Collection
    {
        if (! empty($this->plugins)) {
            $this->debug('Returning cached plugins');

            return collect($this->plugins);
        }

        $this->debug('Starting plugin discovery', ['path' => $this->pluginsPath]);

        if (! is_dir($this->pluginsPath)) {
            $this->warning("Plugins directory not found: {$this->pluginsPath}");

            return collect();
        }

        $plugins = [];
        $directories = new \DirectoryIterator($this->pluginsPath);
        $foundDirs = [];

        foreach ($directories as $directory) {
            $foundDirs[] = $directory->getBasename();

            if (! $directory->isDir() || $directory->isDot()) {
                $this->debug('Skipping non-directory or dot file', ['path' => $directory->getPathname()]);

                continue;
            }

            $pluginPath = $directory->getPathname();
            $manifestPath = $pluginPath.'/plugin.json';
            $this->debug('Checking plugin directory', ['path' => $pluginPath]);

            if (! file_exists($manifestPath)) {
                $this->debug("Plugin manifest not found in: {$pluginPath}");
                // Include in results with error
                $plugins[] = [
                    'path' => $pluginPath,
                    'error' => 'Plugin manifest not found',
                    'enabled' => false,
                ];

                continue;
            }

            $this->debug('Found plugin manifest', ['path' => $manifestPath]);
            $plugin = $this->loadPlugin($pluginPath);

            // Always include the plugin in the results, even if there was an error
            // This allows commands to show error information to the user
            if ($plugin) {
                $expectedDirName = $plugin['id'] ?? null;
                $actualDirName = $directory->getBasename();

                if ($expectedDirName && $expectedDirName !== $actualDirName) {
                    $warning = sprintf(
                        'Plugin directory name "%s" does not match plugin ID "%s"',
                        $actualDirName,
                        $expectedDirName
                    );
                    $this->warning($warning);
                    $plugin['warning'] = $warning;
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
                $error = "Plugin manifest missing required 'id' field";
                $this->error($error, ['path' => $manifestPath]);

                return [
                    'path' => $pluginPath,
                    'error' => $error,
                    'enabled' => false,
                ];
            }

            $plugin = array_merge([
                'path' => $pluginPath,
                'enabled' => $manifest['enabled'] ?? false,
            ], $manifest);

            return $plugin;
        } catch (\JsonException $e) {
            $error = 'Invalid JSON in plugin manifest: '.$e->getMessage();
            $this->error($error, [
                'path' => $manifestPath,
                'exception' => $e,
            ]);

            return [
                'path' => $pluginPath,
                'error' => $error,
                'enabled' => false,
            ];
        }
    }

    /**
     * Validate a plugin's structure and return validation result
     *
     * @param  array  $plugin  The plugin data to validate
     * @return array{is_valid: bool, id: ?string, error: ?string} Validation result
     */
    public function validatePlugin(array $plugin): array
    {
        if (! is_array($plugin)) {
            return ['is_valid' => false, 'id' => null, 'error' => 'Invalid plugin data'];
        }

        if (isset($plugin['error'])) {
            return [
                'is_valid' => false,
                'id' => $plugin['id'] ?? null,
                'error' => $plugin['error'],
            ];
        }

        if (empty($plugin['id'])) {
            return [
                'is_valid' => false,
                'id' => null,
                'error' => 'Plugin ID is missing',
            ];
        }

        return [
            'is_valid' => true,
            'id' => $plugin['id'],
            'error' => null,
        ];
    }

    public function registerPlugins()
    {
        $plugins = $this->discover();

        foreach ($plugins as $plugin) {
            if (empty($plugin['enabled']) || empty($plugin['provider'])) {
                continue;
            }

            try {
                // Normalize the plugin namespace
                $pluginNamespace = rtrim($plugin['namespace'] ?? '', '\\').'\\';
                $pluginSrcPath = rtrim($plugin['path'], '/').'/src';

                if (is_dir($pluginSrcPath) && ! empty($pluginNamespace)) {
                    $this->classLoader->addPsr4($pluginNamespace, $pluginSrcPath);
                    // Force the autoloader to re-index
                    $this->classLoader->setUseIncludePath(true);
                }

                if (class_exists($plugin['provider'])) {
                    $this->app->register($plugin['provider']);
                } else {
                    $this->error("Plugin service provider class not found: {$plugin['provider']}", [
                        'plugin' => $plugin['id'] ?? 'unknown',
                        'path' => $plugin['path'] ?? null,
                    ]);
                }
            } catch (\Exception $e) {
                $this->error('Failed to register plugin: '.$e->getMessage(), [
                    'plugin' => $plugin['id'] ?? 'unknown',
                    'exception' => $e,
                ]);
            }
        }
    }
}
