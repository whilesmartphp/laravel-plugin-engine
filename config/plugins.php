<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Plugin Path
    |--------------------------------------------------------------------------
    |
    | This value is the path where your plugins will be located. By default,
    | it's set to the 'plugins' directory in your application's base path.
    |
    */
    'path' => base_path('plugins'),

    /*
    |--------------------------------------------------------------------------
    | Plugin Namespace
    |--------------------------------------------------------------------------
    |
    | This value is the root namespace for your plugins. Each plugin's service
    | provider will be registered under this namespace.
    |
    */
    'namespace' => 'Plugins',

    /*
    |--------------------------------------------------------------------------
    | Log Channel
    |--------------------------------------------------------------------------
    |
    | The log channel the plugin engine writes to. When null, the
    | application's default log channel is used.
    |
    */
    'log_channel' => env('PLUGIN_ENGINE_LOG_CHANNEL'),

    /*
    |--------------------------------------------------------------------------
    | Log Level
    |--------------------------------------------------------------------------
    |
    | The minimum level the plugin engine logs at, independent of the
    | application's log level. Messages below this level are dropped
    | before they reach the channel.
    |
    */
    'log_level' => env('PLUGIN_ENGINE_LOG_LEVEL', 'warning'),
];
