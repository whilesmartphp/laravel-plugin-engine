<?php

// Use Composer's InstalledVersions to determine the project root.
// This is a more reliable and performant method than traversing the filesystem.
if (class_exists(\Composer\InstalledVersions::class)) {
    $projectRoot = \Composer\InstalledVersions::getRootPackage()['install_path'];
} else {
    // If Composer 2's InstalledVersions is not available, we cannot reliably determine the project root.
    // This typically indicates an incomplete Composer setup or an environment not using Composer 2+.
    throw new \RuntimeException('Cannot determine project root. Please ensure Composer is used and its dependencies are installed, or that you are using Composer 2.');
}

// Require the project's autoloader.
$autoloaderPath = $projectRoot.'/vendor/autoload.php';
if (! file_exists($autoloaderPath)) {
    throw new \RuntimeException('Could not find vendor/autoload.php. Please run composer install.');
}
$autoloader = require $autoloaderPath;

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
