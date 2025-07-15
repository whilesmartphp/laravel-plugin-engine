<?php

declare(strict_types=1);

namespace WhileSmart\LaravelPluginEngine\Console\Commands;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class GenerateOpenApiDocsCommand extends PluginCommand
{
    protected $signature = 'plugin:openapi {plugins?* : The names of the plugins to generate documentation for. Leave empty to generate for all plugins}';

    protected $description = 'Generate and merge OpenAPI documentation for all installed plugins.';

    public function handle(): int
    {
        $requestedPlugins = $this->argument('plugins');
        $targetPlugins = collect();

        if (empty($requestedPlugins)) {
            $this->info('Discovering all valid plugins...');
            $allPlugins = $this->pluginManager->discover();
            $targetPlugins = $allPlugins->filter(function ($plugin) {
                $validation = $this->pluginManager->validatePlugin($plugin);
                if (! $validation['is_valid']) {
                    $pluginId = $plugin['path'] ?? 'unknown plugin';
                    $this->warn("Skipping invalid plugin: {$pluginId} - Reason: {$validation['error']}");
                }

                return $validation['is_valid'];
            });
        } else {
            $this->info('Resolving requested plugins...');
            foreach ($requestedPlugins as $pluginName) {
                try {
                    // resolvePlugin already handles validation and throws an exception
                    $targetPlugins->push($this->resolvePlugin($pluginName));
                } catch (\RuntimeException $e) {
                    $this->error($e->getMessage());
                }
            }
        }

        if (empty($targetPlugins)) {
            $this->info('No valid plugins found to generate documentation for.');

            return self::SUCCESS;
        }

        $outputDir = public_path('docs');
        $openapiBin = base_path('vendor/bin/openapi');

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $generatedFiles = [];

        foreach ($targetPlugins as $plugin) {
            $pluginName = $plugin['name'];
            $pluginId = $plugin['id'];
            $pluginPath = $plugin['path'];
            $outputFile = "{$outputDir}/{$pluginId}.json";

            $this->info("Generating OpenAPI for '{$pluginName}' plugin...");

            $bootstrapPath = dirname(__DIR__, 2).'/bootstrap.php';

            $process = new Process([$openapiBin, $pluginPath, '--bootstrap', $bootstrapPath, '--output', $outputFile]);

            try {
                $process->mustRun();
                $this->info("Successfully generated OpenAPI for '{$pluginName}' plugin.");
                if (file_exists($outputFile)) {
                    $this->info("Generated {$outputFile}");
                    $generatedFiles[] = $outputFile;
                }
            } catch (ProcessFailedException $exception) {
                $this->error("Failed to generate OpenAPI for '{$pluginName}' plugin.");
                $this->error('Error Output:');
                $this->line($exception->getProcess()->getErrorOutput());
                $this->error('Standard Output:');
                $this->line($exception->getProcess()->getOutput());
            }
        }

        if (empty($generatedFiles)) {
            $this->info('No plugin documentation was generated.');

            return self::SUCCESS;
        }

        // If no specific plugins were requested, always merge into a single file.
        if (empty($requestedPlugins)) {
            $this->info('Merging plugin OpenAPI files...');
            $base = json_decode(file_get_contents(array_shift($generatedFiles)), true);

            foreach ($generatedFiles as $file) {
                $pluginData = json_decode(file_get_contents($file), true);
                if (isset($pluginData['paths'])) {
                    $base['paths'] = array_merge($base['paths'] ?? [], $pluginData['paths']);
                }
                if (isset($pluginData['components'])) {
                    $base['components'] = array_merge_recursive($base['components'] ?? [], $pluginData['components']);
                }
                if (isset($pluginData['tags'])) {
                    $base['tags'] = array_merge($base['tags'] ?? [], $pluginData['tags']);
                }
                unlink($file);
            }

            $finalOutputFile = "{$outputDir}/plugins.json";
            file_put_contents($finalOutputFile, json_encode($base, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $this->info("Successfully merged plugin documentation into {$finalOutputFile}");
        } else {
            // If specific plugins were requested, the individual files are the final output.
            $this->info('Plugin documentation generated successfully.');
            foreach ($generatedFiles as $file) {
                $this->line("  - Generated: {$file}");
            }
        }

        return self::SUCCESS;
    }
}
