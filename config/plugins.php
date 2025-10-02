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
  | App Environment
  |--------------------------------------------------------------------------
  |
  | This value is used to determine if debug logs are shown
  |
  */
    'environment' => env('APP_ENV', 'production'),
];
