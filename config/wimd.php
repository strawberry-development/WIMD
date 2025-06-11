<?php
return [
    /*
    |--------------------------------------------------------------------------
    | WIMD
    |--------------------------------------------------------------------------
    |
    | This configuration file allows you to customize how WIMD displays
    | seeding performance information in your Laravel application.
    |
    */
    'mode' => 'full',

    /*
    |--------------------------------------------------------------------------
    | Display Settings
    |--------------------------------------------------------------------------
    |
    | Control what components are shown in the seeding report.
    |
    */
    'display' => [
        // Show the detailed table of seeder performance
        'detailed_table' => true,

        // Show system information
        'system_info' => true,

        // Show performance distribution
        'performance_distribution' => true,

        // Show performance charts
        'performance_charts' => true,

        // Show recommendations
        'recommendations' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Styling Options
    |--------------------------------------------------------------------------
    |
    | Control the visual appearance of the report.
    |
    */
    'styling' => [
        // Use emoji characters in output
        'use_emojis' => true,

        // Use colors in output
        'use_colors' => true,

        'progress_format' => [
            'bar' => '[%bar%] %percent:3s%%',
            'base' => '%elapsed:6s% spend / %remaining:-6s% left',
            'full' => '| Memory %memory:6s%s'
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Thresholds
    |--------------------------------------------------------------------------
    |
    | Define thresholds for performance ratings.
    | These values represent records per second.
    |
    */
    'thresholds' => [
        'excellent' => 1000,  // Records/sec for excellent rating
        'good' => 500,        // Records/sec for good rating
        'average' => 100,     // Records/sec for average rating
        'slow' => 10          // Records/sec for slow rating (below is very slow)
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Options
    |--------------------------------------------------------------------------
    |
    | Control logging.
    |
    */
    'logging' => [
        // Log seeding performance to file
        'log_to_file' => false,
        'log_file' => storage_path('logs/wimd-seeding.log')
    ],
];
