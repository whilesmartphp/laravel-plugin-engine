# Laravel Plugin Engine

[![Tests](https://github.com/whilesmart/laravel-plugin-engine/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/whilesmart/laravel-plugin-engine/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

A flexible and powerful plugin system for Laravel applications.

## Features

- Plugin discovery and registration
- Enable/disable plugins
- Plugin dependencies
- Console commands for plugin management
- Event-driven architecture
- Easy to extend

## Installation

1. Install the package via Composer:

```bash
composer require whilesmart/laravel-plugin-engine
```

2. Publish the configuration file (optional):

```bash
php artisan vendor:publish --provider="WhileSmart\LaravelPluginEngine\Providers\PluginServiceProvider" --tag=config
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="WhileSmart\LaravelPluginEngine\Providers\PluginServiceProvider" --tag=config
```

Edit the `config/plugins.php` file to configure the plugin system:

```php
return [
    'path' => base_path('plugins'),  // Path where plugins are stored
    'namespace' => 'Plugins',        // Root namespace for plugins
];
```

## Usage

### Available Commands

- `plugin:list` - List all available plugins
- `plugin:info {id}` - Show information about a plugin
- `plugin:enable {id}` - Enable a plugin
- `plugin:disable {id}` - Disable a plugin
- `plugin:install {package}` - Install a plugin
- `plugin:discover` - Discover and register all available plugins

### Creating a Plugin

1. Create a new directory in the `plugins` directory (or your configured path)
2. Create a `plugin.json` file with the following structure:

```json
{
    "id": "example-plugin",
    "name": "Example Plugin",
    "description": "A sample plugin",
    "version": "1.0.0",
    "namespace": "Plugins\\Example",
    "provider": "Plugins\\Example\\ExampleServiceProvider",
    "enabled": true,
    "requires": {
        "php": ">=8.1",
        "laravel/framework": "^10.0"
    }
}
```

3. Create a service provider for your plugin:

```php
<?php

namespace Plugins\Example;

use Illuminate\Support\ServiceProvider;

class ExampleServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Register bindings
    }

    public function boot()
    {
        // Boot logic
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'example');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
```

## Plugin Structure

```
plugins/
  example-plugin/
    src/
      Http/
        Controllers/
      Models/
      Providers/
        ExampleServiceProvider.php
    resources/
      views/
    routes/
      web.php
    database/
      migrations/
    plugin.json
    README.md
```

## Events

The plugin system dispatches several events that you can listen for:

- `WhileSmart\PluginEngine\Events\PluginEnabling` - Fired before a plugin is enabled
- `WhileSmart\PluginEngine\Events\PluginEnabled` - Fired after a plugin is enabled
- `WhileSmart\PluginEngine\Events\PluginDisabling` - Fired before a plugin is disabled
- `WhileSmart\PluginEngine\Events\PluginDisabled` - Fired after a plugin is disabled
- `WhileSmart\PluginEngine\Events\PluginInstalled` - Fired after a plugin is installed
- `WhileSmart\PluginEngine\Events\PluginDiscovered` - Fired when a plugin is discovered

## License

This project is open-source and licensed under the [MIT License](./LICENSE). 
