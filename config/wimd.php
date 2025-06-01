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
    | Memory Usage Thresholds
    |--------------------------------------------------------------------------
    |
    | Define thresholds for memory usage warnings during seeding operations.
    | Values can be specified in bytes, KB (suffix: K), MB (suffix: M), or GB (suffix: G).
    |
    */
    'memory' => [
        // Enable memory usage warnings
        'warnings_enabled' => true,

        // Memory thresholds that trigger different warning levels
        'thresholds' => [
            'notice' => '50M',    // Memory usage threshold for notice level
            'warning' => '100M',  // Memory usage threshold for warning level
            'critical' => '200M', // Memory usage threshold for critical level
        ],

        // Per-record memory thresholds (in KB per record)
        'per_record' => [
            'efficient' => 1,     // KB/record threshold for efficient rating
            'acceptable' => 5,    // KB/record threshold for acceptable rating
            'concerning' => 20,   // KB/record threshold for concerning rating
            'excessive' => 50     // KB/record threshold for excessive rating (above is critical)
        ],

        // Options for memory warning behavior
        'options' => [
            // Display memory warnings during seeding process
            'display_during_seeding' => true,

            // Log excessive memory usage
            'log_excessive_usage' => true,

            // Maximum memory that can be allocated before seeding is aborted (set to null to disable)
            'abort_threshold' => null, // Example: '500M'

            // Show memory optimization recommendations for inefficient seeders
            'show_optimization_tips' => true,

            // Track memory usage over time (enables more detailed reports)
            'track_usage_over_time' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Options
    |--------------------------------------------------------------------------
    |
    | Advanced settings for troubleshooting and debugging.
    |
    */
    'debug' => [
        // Show additional debug information
        'verbose' => false,
        // Log seeding performance to file
        'log_to_file' => false,
        'log_file' => storage_path('logs/wimd-seeding.log'),
    ],
];
