![Cover](/.github/header.jpg)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/strawberrydev/wimd.svg?style=flat-square)](https://packagist.org/packages/strawberrydev/wimd)
[![Total Downloads](https://img.shields.io/packagist/dt/strawberrydev/wimd.svg?style=flat-square)](https://packagist.org/packages/strawberrydev/wimd)
[![License](https://img.shields.io/packagist/l/strawberrydev/wimd.svg?style=flat-square)](https://packagist.org/packages/strawberrydev/wimd)

## Introduction

Have you ever wondered where your data is while seeding? Me too. That's why there's WIMD (Where Is My Data), a Laravel package that enhances your database seeding process with monitoring. It provides real-time tracking of seeding performance, detailed metrics, and insightful reporting to help you optimize your database seeding operations.

## Warning

**This is the first release and *not a stable version*. It is meant to showcase the current state of the project and its progress. Updates will be made.**  
Some features don't work yet and will be properly implemented in future updates.

## Requirements

- PHP 8.1+
- Laravel 9.0+

## Installation

You can install the package via composer:

```bash
composer require strawberrydev/wimd
````
Or for alpha version
```
composer require strawberrydev/wimd:"dev-main"
```

## Configuration

Publish the configuration file using:

```bash
php artisan vendor:publish --tag=wimd-config
```

This will create a `config/wimd.php` file where you can customize the package behavior.

## Usage

### DatabaseSeeder

```php
<?php

namespace Database\Seeders;

use Wimd\Template\WimdDatabaseSeeder;

class DatabaseSeeder extends WimdDatabaseSeeder
{
    public function run()
    {
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

Use this line if you want to manually display the report (not shown by default unless using the specific command):

```php
$this->displayWimdReport();
```

### Seeder

Extend `Wimd\Template\WimdSeeder` instead of Laravel's `Seeder`:

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Wimd\Template\WimdSeeder;

class UserSeeder extends WimdSeeder
{
    protected function prepare(): void
    {
        $this->totalItems = 1000;
        $this->fullItems = 1000000;
        $this->lightItems = 250;
        $this->batchSize = 77;
    }

    protected function seed(): void
    {
        $admin = [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $this->insertItem('users', $admin);

        $this->createWithFactory(User::class, $this->totalItems - 1);
    }
}
```

### Command

Use the WIMD-specific command:

```bash
php artisan db:wimd-seed
```

Specify a seeder:

```bash
php artisan db:wimd-seed --class=Database\\Seeders\\UserSeeder
```

Use light mode:

```bash
php artisan db:wimd-seed --wimd-mode=light
```

## Advanced Usage

### Using the Built-in Batch Methods

#### batchInsert()

```php
$this->batchInsert('users', $usersArray, $batchSize);
```

#### insertItem() and insertItemAndGetId()

```php
$this->insertItem('users', $userData);
```

#### createWithFactory()

```php
$this->createWithFactory(User::class, 50);
```

## Output Renderers

![showcase](/.github/showcase.jpg)

WIMD includes a comprehensive output with detailed metrics and system information.

Report example
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

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

![Footer](/.github/footer.jpg)
