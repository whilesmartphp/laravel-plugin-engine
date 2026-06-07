<?php

namespace WhileSmart\LaravelPluginEngine\Services;

use Composer\Autoload\ClassLoader;
use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class PluginManager
{
    protected Application $app;

    protected string $pluginsPath;

    protected array $plugins = [];

    protected ClassLoader $classLoader;

    protected $output;

    protected LoggerInterface $logger;

    public function __construct(Application $app, $output = null, ?LoggerInterface $logger = null)
    {
        $this->app = $app;
        $this->pluginsPath = $app['config']->get('plugins.path') ?? base_path('plugins');
        $this->classLoader = require base_path('vendor/autoload.php');
        $this->output = $output ?? new OutputStyle(new ArrayInput([]), new NullOutput);
        $this->logger = $logger ?? ($app->bound('log') ? $app->make('log') : new NullLogger);
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
            if ($this->pluginsAreCached()) {
                $this->cachePlugins();
            } else {
                $this->discover(true);
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to update plugin manifest: '.$e->getMessage(), [
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

    /**
     * Discover all plugins.
     *
     * Resolution order: in-memory cache, compiled cache file, filesystem scan.
     * Pass $fresh to force a filesystem scan.
     */
    public function discover(bool $fresh = false): Collection
    {
        if ($fresh) {
            $this->plugins = [];
        } elseif (! empty($this->plugins)) {
            return collect($this->plugins);
        } elseif ($this->pluginsAreCached()) {
            $this->plugins = $this->loadCachedPlugins();

            return collect($this->plugins);
        }

        if (! is_dir($this->pluginsPath)) {
            // A plugin-less install is a normal state, not a problem worth warning about
            $this->logger->debug("Plugins directory not found: {$this->pluginsPath}");

            return collect();
        }

        $plugins = [];
        $directories = new \DirectoryIterator($this->pluginsPath);

        foreach ($directories as $directory) {
            if (! $directory->isDir() || $directory->isDot()) {
                continue;
            }

            $pluginPath = $directory->getPathname();
            $manifestPath = $pluginPath.'/plugin.json';

            if (! file_exists($manifestPath)) {
                // Include in results with error
                $plugins[] = [
                    'path' => $pluginPath,
                    'error' => 'Plugin manifest not found',
                    'enabled' => false,
                ];

                continue;
            }

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
                    $this->logger->warning($warning);
                    $plugin['warning'] = $warning;
                }

                $plugins[] = $plugin;
            }
        }

        $this->plugins = $plugins;
        $this->logger->debug('Plugin discovery complete', [
            'path' => $this->pluginsPath,
            'found' => count($plugins),
        ]);

        return collect($plugins);
    }

    /**
     * Determine if a compiled plugin cache file exists.
     */
    public function pluginsAreCached(): bool
    {
        return is_file($this->getCachedPluginsPath());
    }

    /**
     * Get the path to the compiled plugin cache file.
     */
    public function getCachedPluginsPath(): string
    {
        return $this->app->bootstrapPath('cache/plugins.php');
    }

    /**
     * Compile the discovered plugins into a cache file and return its path.
     */
    public function cachePlugins(): string
    {
        $plugins = $this->discover(true)->all();
        $path = $this->getCachedPluginsPath();

        File::ensureDirectoryExists(dirname($path));
        File::put($path, '<?php return '.var_export($plugins, true).';'.PHP_EOL);

        return $path;
    }

    /**
     * Remove the compiled plugin cache file.
     */
    public function clearCachedPlugins(): bool
    {
        return File::delete($this->getCachedPluginsPath());
    }

    /**
     * Load plugins from the compiled cache file.
     */
    protected function loadCachedPlugins(): array
    {
        $plugins = require $this->getCachedPluginsPath();

        return is_array($plugins) ? $plugins : [];
    }

    protected function loadPlugin(string $pluginPath): ?array
    {
        $manifestPath = $pluginPath.'/plugin.json';

        try {
            $manifest = json_decode(File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);

            if (! isset($manifest['id'])) {
                $error = "Plugin manifest missing required 'id' field";
                $this->logger->error($error, ['path' => $manifestPath]);

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
            $this->logger->error($error, [
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
                    $this->logger->error("Plugin service provider class not found: {$plugin['provider']}", [
                        'plugin' => $plugin['id'] ?? 'unknown',
                        'path' => $plugin['path'] ?? null,
                    ]);
                }
            } catch (\Exception $e) {
                $this->logger->error('Failed to register plugin: '.$e->getMessage(), [
                    'plugin' => $plugin['id'] ?? 'unknown',
                    'exception' => $e,
                ]);
            }
        }
    }
}
