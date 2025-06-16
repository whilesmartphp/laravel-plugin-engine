<?php

namespace Trakli\PluginEngine\Console\Commands;

use Illuminate\Console\Command;
use Trakli\PluginEngine\Services\PluginManager;

abstract class PluginCommand extends Command
{
    protected PluginManager $pluginManager;

    public function __construct(PluginManager $pluginManager)
    {
        parent::__construct();
        $this->pluginManager = $pluginManager;
    }
    
    /**
     * Validate a plugin using PluginManager's validation
     * 
     * @param array $plugin The plugin data to validate
     * @return array{is_valid: bool, id: ?string, error: ?string} Validation result
     */
    protected function validatePlugin(array $plugin): array
    {
        return $this->pluginManager->validatePlugin($plugin);
    }

    /**
     * Get a plugin by ID or short name
     *
     * @param  string  $pluginId  Plugin ID or short name
     *
     * @throws \RuntimeException If plugin is not found or multiple plugins match
     */
    protected function resolvePlugin(string $pluginId): array
    {
        $plugin = $this->pluginManager->findPlugin($pluginId);

        if (! $plugin) {
            // Plugin directory doesn't exist
            $suggestions = $this->getPluginSuggestions($pluginId);
            $message = "Plugin '{$pluginId}' not found.";

            if (! empty($suggestions)) {
                $message .= " Did you mean one of these?\n  -".implode("\n  - ", $suggestions);
            } else {
                $message .= ' Use `plugin:list` to see available plugins.';
            }

            throw new \RuntimeException($message);
        }

        // Plugin exists but might have errors
        $validation = $this->validatePlugin($plugin);
        
        if (!$validation['is_valid']) {
            $error = $validation['error'] ?? 'Unknown error';
            $pluginId = $validation['id'] ?? $pluginId;
            throw new \RuntimeException("Plugin '{$pluginId}' has errors: {$error}");
        }

        return $plugin;
    }

    /**
     * Get suggested plugin IDs based on input
     */
    protected function getPluginSuggestions(string $input): array
    {
        $allPlugins = $this->pluginManager->discover();
        $input = strtolower($input);
        $suggestions = [];

        foreach ($allPlugins as $plugin) {
            $validation = $this->validatePlugin($plugin);
            
            // Include plugins with errors in suggestions if their ID matches
            if (!$validation['is_valid'] && $validation['id'] !== null) {
                try {
                    $pluginId = strtolower((string)$validation['id']);
                    if (str_contains($pluginId, $input)) {
                        $suggestions[] = $validation['id'] . ' (error: ' . ($validation['error'] ?? 'unknown') . ')';
                    }
                } catch (\Throwable $e) {
                    continue;
                }
                continue;
            }
            
            // Process valid plugins
            if ($validation['is_valid']) {
                try {
                    $pluginId = strtolower((string)$validation['id']);
                    if (str_contains($pluginId, $input)) {
                        $suggestions[] = $validation['id'];
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }

        return array_slice($suggestions, 0, 5);
    }
}
