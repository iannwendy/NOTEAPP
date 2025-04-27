<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HTTPS Settings
    |--------------------------------------------------------------------------
    |
    | These settings determine how the application handles HTTPS requests.
    |
    */
    
    /*
     * Force HTTPS in production environment
     */
    'force_https' => env('FORCE_HTTPS', true),
    
    /*
     * HSTS max age in seconds (default to 1 year)
     */
    'hsts_max_age' => 31536000,
    
    /*
     * Include subdomains in HSTS header
     */
    'hsts_include_subdomains' => true,
    
    /*
     * Preload HSTS header
     */
    'hsts_preload' => false,
]; 