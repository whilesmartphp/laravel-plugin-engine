<?php

// Determine the project root directory by navigating up from this script's location.
$projectRoot = dirname(__DIR__, 2);

$autoloader = require $projectRoot.'/vendor/autoload.php';

$pluginsDir = $projectRoot.'/plugins';

if (! is_dir($pluginsDir)) {
    return;
}

$pluginJsonFiles = glob($pluginsDir.'/*/plugin.json');

foreach ($pluginJsonFiles as $file) {
    $content = @file_get_contents($file);
    if ($content === false) {
        error_log("Plugin Autoload Bootstrap: Could not read file: {$file}");

        continue;
    }

    $pluginConfig = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Plugin Autoload Bootstrap: Invalid JSON in file: {$file}. Error: ".json_last_error_msg());

        continue;
    }

    // Ensure config is a valid array and namespace is set before proceeding.
    if (is_array($pluginConfig) && ! empty($pluginConfig['namespace'])) {
        $namespace = rtrim($pluginConfig['namespace'], '\\').'\\';
        $path = dirname($file).'/src';

        if (is_dir($path)) {
            $autoloader->addPsr4($namespace, $path);
        }
    }
}
