<?php
/**
 * SC Datasheet Generator — Configuration
 *
 * Central configuration file for the application
 */

return [
    // Application settings
    'app_name' => 'SC Datasheet Generator',
    'app_version' => '1.0.0',
    'app_url' => 'http://localhost:8000',

    // Paths (relative to project root)
    'paths' => [
        'data_file' => 'data/fixtures_app_data.json',
        'images_dir' => 'app/images',
        'assets_dir' => 'assets',
        'template_dir' => 'app/images/template',
    ],

    // API settings
    'api' => [
        'prefix' => '/api',
        'enable_cors' => true,
        'cache_enabled' => true,
    ],

    // PDF settings
    'pdf' => [
        'page_size' => 'LETTER', // LETTER or A4
        'margin_top' => 28,
        'margin_right' => 28,
        'margin_bottom' => 28,
        'margin_left' => 28,
        'font_family' => 'helvetica',
        'font_size' => 12,
    ],

    // Brand settings (from sc-brand.md)
    'brand' => [
        'company_name' => 'Short Circuit Company',
        'primary_color' => '#eb1b26',
        'dark_color' => '#a40e16',
        'black' => '#000000',
        'white' => '#ffffff',
        'gray' => '#cccccc',
    ],

    // Debug settings
    'debug' => true,
    'display_errors' => true,
];
