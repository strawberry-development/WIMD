![Cover](/.github/header.png)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/strawberrydev/wimd.svg?style=flat-square)](https://packagist.org/packages/strawberrydev/wimd)
[![Total Downloads](https://img.shields.io/packagist/dt/strawberrydev/wimd.svg?style=flat-square)](https://packagist.org/packages/strawberrydev/wimd)
[![License](https://img.shields.io/packagist/l/strawberrydev/wimd.svg?style=flat-square)](https://packagist.org/packages/strawberrydev/wimd)
## Introduction

Have you ever wondering where is your data while seeding ? Me too, that's why there is WIMD (Where Is My Data), a Laravel package that enhances your database seeding process with monitoring. It provides real-time tracking of seeding performance, detailed metrics, and insightful reporting to help you optimize your database seeding operations.

## Warning
**This is the first release and *not a stable version*. It is meant to showcase the current state of the project and its progress. Currently, the project works well on Linux distributions, but I have encountered several problems on Windows. Updates will be made.**
Some features don't work yet and will be properly implemented in future updates.

## Requirements

- PHP 7.4+
- Laravel 10.0+

## Installation

You can install the package via composer:

```bash
composer require strawberrydev/wimd
```

## Configuration

Publish the configuration file using:

```bash
php artisan vendor:publish --tag=wimd-config
```

This will create a `config/wimd.php` file where you can customize the package behavior.

### Configuration Options

```php
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
        // Use Unicode characters for borders
        'use_unicode' => true,
        // Use emoji characters in output
        'use_emojis' => true,
        // Use colors in output
        'use_colors' => true,
        // Default theme colors
        'theme' => [
            'primary' => 'green',
            'secondary' => 'blue',
            'success' => 'green',
            'warning' => 'yellow',
            'danger' => 'red',
            'neutral' => 'white',
            'muted' => 'gray',
        ],
        'console_width' => 100,
        'border_style' => 'rounded',
        'progress_format' => [
            'base' => '🍃 [%bar%] %percent:3s%% | ⏳ %elapsed:6s% | ⏱️ %remaining:-6s% | seeding %max%',
            'full' => ' | 🧠 %memory:6s%'
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
```

## Basic Usage

### Step 1
```php
<?php

namespace Database\Seeders;

use Wimd\Template\WimdDatabaseSeeder;

class DatabaseSeeder extends WimdDatabaseSeeder
{
    public function run()
    {
        // The WIMD manager automatically tracks the overall execution time
        // You just need to call the seeders as usual
        $this->call([
            UserSeeder::class,
            CategoriesSeeder::class,
            ProductsSeeder::class,
            TagsSeeder::class,
            ProductTagSeeder::class,
            UserDetailsSeeder::class,
            ReviewsSeeder::class,
        ]);

        // Optional
        $this->displayWimdReport();
    }
}
```

You can use this line if you want to manually display the report. By default, it is not displayed unless you use the specific command
```PHP
$this->displayWimdReport();
```

### Step 2: Create a WIMD Seeder

Instead of extending the standard Laravel `Seeder` class, extend `Wimd\Template\WimdSeeder`:

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Wimd\Template\WimdSeeder;

class UserSeeder extends WimdSeeder
{
    /**
     * Prepare the seeder (calculate total number of records to create)
     * This is required by the WIMD framework to set up the progress bar
     *
     * @return void
     */
    protected function prepare(): void
    {
        // totalItems will be overridden by fullItems or lightItems
        // $this->totalItems = 1000;
        $this->fullItems = 1000000;
        $this->lightItems = 250;
        $this->batchSize = 100;
    }

    /**
     * Seed the users table
     *
     * @return void
     * @throws \Exception
     * @throws \Throwable
     */
    protected function seed(): void
    {
        // Create admin user
        $admin = [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Insert admin user and track progress
        $this->insertItem('users', $admin);

        // Create regular users with factory
        // Using the WIMD wrapper for factory creation that tracks the progress automatically
        $this->createWithFactory(User::class, $this->totalItems-1);
    }
}

```

### Step 3: Run the Seeder with WIMD Monitoring

Use the WIMD-specific seeding command to get the monitorinr result:

```bash
php artisan db:wimd-seed
```

You can also specify a specific seeder:

```bash
php artisan db:wimd-seed --class=Database\\Seeders\\UserSeeder
```

To use light mode monitoring:

```bash
php artisan db:wimd-seed --wimd-mode=light
```

## Advanced Usage

### Using the Built-in Batch Methods

WIMD provides several helper methods for batch processing:

#### batchInsert()
For a lot of data
```php
// Insert data in batches with automatic progress tracking
$this->batchInsert('users', $usersArray, $batchSize);
```

#### insertItem() and insertItemAndGetId()
For individual data
```php
// Insert a single item with progress tracking
$this->insertItem('users', $userData);
```

#### createWithFactory()
To create with Factory & having a batch insertion
```php
// Create models using Laravel factories with progress tracking
$users = $this->createWithFactory(User::class, 50);
```


## Output Renderers

![screen shot](/.github/showcase.png)

WIMD includes a comprehensive output with detailed metrics and system information:
it can be call from the command otherwhise you can implement it manually and use as you do the seeding.
```PHP
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Wimd\Facades\Wimd;
use Wimd\Traits\ReportableTrait;

class DatabaseSeeder extends Seeder
{
    use ReportableTrait;

    public function run()
    {
        // The WIMD manager automatically tracks the overall execution time
        // You just need to call the seeders as usual
        $this->call([
            UserSeeder::class,
            CategoriesSeeder::class,
            ProductsSeeder::class,
            TagsSeeder::class,
            ProductTagSeeder::class,
            UserDetailsSeeder::class,
            ReviewsSeeder::class,
        ]);

        $this->displayWimdReport();
    }
}
```
Example given at the end
```
   INFO  WIMD SEEDING REPORT

   REPORT  PERFORMANCE SUMMARY

  Execution Time .......................................................................................................................... 0.63 sec
  Records Added (~0.52 ms/record) ............................................................................................................. 1217
  Overall Speed ............................................................................................................. 1926.85 records/second
  Performance Rating ..................................................................................................................... Excellent
  Fastest Seeder (21354.73 records/sec) .............................................................................................. ReviewsSeeder
  Slowest Seeder (181.5 records/sec) .............................................................................................. ProductTagSeeder
  Speed Variance ................................................................................... 11665.7% difference between fastest and slowest

   REPORT  PERFORMANCE DISTRIBUTION

  Excellent ............... ■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■ .......................................... 100% (7 seeders)

   REPORT  TOP SEEDERS BY PERFORMANCE

  ReviewsSeeder ........... ■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■■ .................................. 21,354.73 records/second
  UserDetailsSeeder ....... ■■■■■■■■■■■■■............................................... ................................... 4,695.92 records/second
  CategoriesSeeder ........ ■........................................................... ..................................... 268.59 records/second
  UserSeeder .............. ■........................................................... ..................................... 257.92 records/second
  ProductsSeeder .......... ■........................................................... ..................................... 206.39 records/second

   REPORT  DETAILED SEEDER METRICS

  Seeder Records Time (sec) Records/sec Rating......................................................................................................
  #1 ReviewsSeeder               1,000           0.0468       21354.73        Excellent
  #2 UserDetailsSeeder           100             0.0213       4695.92         Excellent
  #3 CategoriesSeeder            5               0.0186       268.59          Average
  UserSeeder                     100             0.3877       257.92          Average
  ProductsSeeder                 4               0.0194       206.39          Average
  TagsSeeder                     4               0.0195       205.57          Average
  ProductTagSeeder               4               0.022        181.5           Average
  MokeSeeder                     0               0            0               N/A
  MokingBirdSeeder               0               0            0               N/A

   REPORT  RECOMMENDATIONS

  💡 1. No critical issues detected in seeding performance


   INFO  WIMD report complete — thanks for using!
```

## Performance Ratings

WIMD rates your seeders' performance based on records per second:

| Rating | Threshold | Description |
|--------|-----------|-------------|
| Excellent | > 1000 records/sec | Highly optimized seeder |
| Good | > 500 records/sec | Well-performing seeder |
| Average | > 100 records/sec | Acceptable performance |
| Slow | > 10 records/sec | Needs optimization |
| Very Slow | ≤ 10 records/sec | Critical performance issues |

You can change this in the config files.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

![Footer](/.github/footer.png)
