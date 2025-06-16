<?php

namespace Trakli\PluginEngine\Console\Commands;

use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class InstallCommand extends PluginCommand
{
    protected $signature = 'plugin:install
        {package : The package name (vendor/name) or URL of the plugin to install}
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

    public function handle()
    {
        $package = $this->argument('package');

        try {
            $this->info("Installing plugin: {$package}");

            // Check if it's a URL or a package name
            if (filter_var($package, FILTER_VALIDATE_URL)) {
                return $this->installFromUrl($package);
            }

            // Otherwise, treat as a Composer package
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

            // Install the plugin
            return $this->installFromPath($tempDir, $pluginName);

        } finally {
            // Clean up
            $this->removeDirectory($tempDir);
        }
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
        $pluginsPath = config('plugins.path', base_path('plugins'));
        $targetPath = rtrim($pluginsPath, '/').'/'.$pluginName;

        // Create plugins directory if it doesn't exist
        if (! is_dir($pluginsPath)) {
            mkdir($pluginsPath, 0755, true);
        }

        // Check if plugin already exists
        if (is_dir($targetPath)) {
            throw new \RuntimeException("Plugin directory already exists: {$targetPath}");
        }

        // Move the plugin to the plugins directory
        rename($path, $targetPath);

        $this->info("Plugin installed successfully to: {$targetPath}");

        // Run plugin discovery
        $this->call('plugin:discover');

        return 0;
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
