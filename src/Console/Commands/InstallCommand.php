<?php

namespace WhileSmart\LaravelPluginEngine\Console\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use WhileSmart\LaravelPluginEngine\Services\ComposerRunner;
use WhileSmart\LaravelPluginEngine\Services\PluginManager;

class InstallCommand extends PluginCommand
{
    protected $signature = 'plugin:install
        {package : The package name (vendor/name), URL, or local path of the plugin to install}
        {--dev : Install development dependencies}'
        .'{--no-dev : Do not install development dependencies}'
        .'{--no-scripts : Skip running installation scripts}'
        .'{--no-plugins : Skip installing plugins}'
        .'{--no-scripts : Skip running scripts}'
        .'{--prefer-source : Install packages from source when possible}'
        .'{--prefer-dist : Install packages from dist when possible}'
        .'{--optimize-autoloader : Optimize autoloader during autoloader dump}'
        .'{--classmap-authoritative : Autoload classes from the classmap only. Implicitly enables `--optimize-autoloader`}'
        .'{--apcu-autoloader : Use APCu to cache found/not-found classes}';

    protected $description = 'Install a plugin';

    public function __construct(PluginManager $pluginManager, protected ComposerRunner $composer)
    {
        parent::__construct($pluginManager);
    }

    public function handle()
    {
        $package = $this->argument('package');

        try {
            $this->info("Installing plugin: {$package}");

            // A remote repository URL: clone it, then install from the clone.
            if (filter_var($package, FILTER_VALIDATE_URL)) {
                return $this->installFromUrl($package);
            }

            // A local directory holding the plugin source: copy it into place.
            if (is_dir($package)) {
                return $this->installFromLocalPath($package);
            }

            // Otherwise, treat as a Composer package.
            return $this->installWithComposer($package);

        } catch (\Exception $e) {
            $this->error('Failed to install plugin: '.$e->getMessage());
            if ($this->getOutput()->isVerbose()) {
                $this->error($e->getTraceAsString());
            }

            return 1;
        }
    }

    protected function installFromUrl(string $url): int
    {
        $this->info("Downloading plugin from URL: {$url}");

        // Extract the plugin name from the URL or generate a random one
        $pluginName = basename(parse_url($url, PHP_URL_PATH), '.git');
        $pluginName = preg_replace('/[^a-z0-9\-_]/i', '', $pluginName);

        if (empty($pluginName)) {
            $pluginName = 'plugin-'.Str::random(8);
        }

        $tempDir = sys_get_temp_dir().'/trakli-plugin-'.Str::random(8);
        mkdir($tempDir, 0755, true);

        try {
            // Clone the repository
            $process = new Process(['git', 'clone', '--depth', '1', $url, $tempDir]);
            $process->setTimeout(300);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new \RuntimeException($process->getErrorOutput());
            }

            // Move the clone into the plugins directory and finish the install.
            return $this->installFromPath($tempDir, $pluginName);

        } finally {
            // Clean up
            $this->removeDirectory($tempDir);
        }
    }

    protected function installFromLocalPath(string $path): int
    {
        $path = rtrim($path, '/');
        $pluginName = basename($path);

        $this->info("Installing plugin from path: {$path}");

        $targetPath = $this->resolveTargetPath($pluginName);

        // Copy rather than move, so the user's source directory is left intact.
        File::copyDirectory($path, $targetPath);

        return $this->finalizeInstall($targetPath);
    }

    protected function installWithComposer(string $package): int
    {
        $this->info("Installing plugin using Composer: {$package}");

        // Build the Composer command
        $command = array_merge(
            ['composer', 'require', $package],
            $this->getComposerOptions()
        );

        $process = new Process($command, base_path(), null, null, null);
        $process->setTty(Process::isTtySupported());

        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            throw new \RuntimeException("Failed to install package: {$package}");
        }

        return 0;
    }

    protected function installFromPath(string $path, string $pluginName): int
    {
        $targetPath = $this->resolveTargetPath($pluginName);

        // Move the plugin to the plugins directory
        rename($path, $targetPath);

        return $this->finalizeInstall($targetPath);
    }

    /**
     * Resolve and validate the destination directory for a plugin, creating
     * the plugins directory when it does not yet exist.
     */
    protected function resolveTargetPath(string $pluginName): string
    {
        $pluginsPath = config('plugins.path', base_path('plugins'));
        $targetPath = rtrim($pluginsPath, '/').'/'.$pluginName;

        if (! is_dir($pluginsPath)) {
            mkdir($pluginsPath, 0755, true);
        }

        if (is_dir($targetPath)) {
            throw new \RuntimeException("Plugin directory already exists: {$targetPath}");
        }

        return $targetPath;
    }

    /**
     * Shared tail of every non-Composer install: install the plugin's own
     * dependencies, then discover it.
     */
    protected function finalizeInstall(string $targetPath): int
    {
        $this->info("Plugin installed successfully to: {$targetPath}");

        $this->installPluginDependencies($targetPath);

        // Run plugin discovery
        $this->call('plugin:discover');

        return 0;
    }

    /**
     * Install the dependencies a plugin declares in its own composer.json.
     *
     * The webservice loads a plugin by reading plugin.json and PSR-4
     * registering its namespace; it never reads the plugin's composer.json.
     * So without this step the plugin's `require` is never resolved and its
     * classes are missing at runtime.
     */
    protected function installPluginDependencies(string $targetPath): void
    {
        $composerFile = $targetPath.'/composer.json';

        if (! is_file($composerFile)) {
            return;
        }

        $manifest = json_decode((string) file_get_contents($composerFile), true);

        if (! is_array($manifest)) {
            $this->warn('Plugin composer.json is not valid JSON; skipping dependency install.');

            return;
        }

        $packages = $this->resolvablePackages($manifest['require'] ?? []);

        if (empty($packages)) {
            return;
        }

        // Register any repositories the plugin declares (VCS, path, ...) so its
        // requires can be resolved against them.
        foreach (($manifest['repositories'] ?? []) as $name => $repository) {
            $repoName = is_string($name) ? $name : 'plugin-'.substr(md5(json_encode($repository)), 0, 8);

            $this->composer->run(
                ['composer', 'config', '--no-interaction', 'repositories.'.$repoName, json_encode($repository)],
                base_path()
            );
        }

        // When the host merges plugin composer.json files (the wikimedia
        // composer-merge-plugin pattern), the plugin's `require` is already
        // part of the resolved set, so a targeted `composer update` installs
        // it while leaving ownership with the plugin. Otherwise fall back to
        // `composer require`, which resolves the packages against the host's
        // existing constraints and records them in the root composer.json.
        $verb = $this->hostMergesPluginManifests() ? 'update' : 'require';

        $command = array_merge(
            ['composer', $verb, '--no-interaction'],
            $packages,
            $this->getComposerOptions()
        );

        $this->info('Installing plugin dependencies: '.implode(', ', $packages));

        $ok = $this->composer->run($command, base_path(), function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if (! $ok) {
            throw new \RuntimeException('Failed to install plugin dependencies.');
        }
    }

    /**
     * Reduce a composer `require` map to the real packages to install,
     * dropping platform constraints (php, ext-*, lib-*) that Composer
     * checks but never downloads.
     *
     * @param  array<string, string>  $require
     * @return array<int, string>
     */
    protected function resolvablePackages(array $require): array
    {
        $packages = [];

        foreach ($require as $name => $constraint) {
            if ($name === 'php' || Str::startsWith($name, ['ext-', 'lib-'])) {
                continue;
            }

            $packages[] = $name.':'.$constraint;
        }

        return $packages;
    }

    /**
     * Whether the host application merges plugin composer.json files into its
     * own dependency resolution (wikimedia/composer-merge-plugin configured to
     * include the plugins path).
     */
    protected function hostMergesPluginManifests(): bool
    {
        $rootComposer = base_path('composer.json');

        if (! is_file($rootComposer)) {
            return false;
        }

        $config = json_decode((string) file_get_contents($rootComposer), true);
        $includes = $config['extra']['merge-plugin']['include'] ?? [];
        $includes = is_array($includes) ? $includes : [$includes];

        if (empty($includes)) {
            return false;
        }

        $pluginsPath = config('plugins.path', base_path('plugins'));
        $relative = trim(str_replace('\\', '/', Str::after($pluginsPath, base_path())), '/');

        foreach ($includes as $pattern) {
            $normalizedPattern = ltrim(str_replace('\\', '/', $pattern), './');

            if ($relative !== '' && Str::startsWith($normalizedPattern, $relative)) {
                return true;
            }
        }

        return false;
    }

    protected function getComposerOptions(): array
    {
        $options = [];

        // Add boolean options
        foreach (['dev', 'no-dev', 'no-scripts', 'no-plugins', 'prefer-source', 'prefer-dist', 'optimize-autoloader', 'classmap-authoritative', 'apcu-autoloader'] as $option) {
            if ($this->option($option)) {
                $options[] = '--'.$option;
            }
        }

        return $options;
    }

    protected function removeDirectory(string $directory): bool
    {
        if (! is_dir($directory)) {
            return false;
        }

        $files = array_diff(scandir($directory), ['.', '..']);

        foreach ($files as $file) {
            $path = $directory.'/'.$file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        return rmdir($directory);
    }
}
