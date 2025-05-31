![Cover](/.github/header.jpg)

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
composer require strawberrydev/wimd:dev-main
```

## Configuration

Publish the configuration file using:

```bash
php artisan vendor:publish --tag=wimd-config
```

This will create a `config/wimd.php` file where you can customize the package behavior.

w### Step 1
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
        
        // By default you can use totalItems
        $this->totalItems = 1000;
        
        // Or you can be more specific when doing light / full seed
        $this->fullItems = 1000000;
        $this->lightItems = 250;
        
        // You can use a specific batchSize. I.E. with heavy data.
        $this->batchSize = 77;
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

![showcase](/.github/showcase.jpg)

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

### Report example
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
### Loading example
```
   INFO  Seeding database.

  User Seeder (Mode: full | Target: 100) ................................................................................................... RUNNING
  [##################################################] 100% ......................................... 3 secs spend / < 1 sec left | Memory 30.0 MiBs
  User Seeder (Items: 100 | Per second: 26.0/s) ............................................................................................... DONE

  Categories Seeder (Mode: full | Target: 5) ............................................................................................... RUNNING
  [##################################################] 100% ........................................ < 1 sec spend / < 1 sec left | Memory 30.0 MiBs
  Categories Seeder (Items: 5 | Per second: 291.6/s) .......................................................................................... DONE

  Products Seeder (Mode: full | Target: 4) ................................................................................................. RUNNING
  [##################################################] 100% ........................................ < 1 sec spend / < 1 sec left | Memory 30.0 MiBs
  Products Seeder (Items: 4 | Per second: 207.0/s) ............................................................................................ DONE

  Tags Seeder (Mode: full | Target: 4) ..................................................................................................... RUNNING
  [##################################################] 100% ........................................ < 1 sec spend / < 1 sec left | Memory 30.0 MiBs
  Tags Seeder (Items: 4 | Per second: 211.6/s) ................................................................................................ DONE

  ProductTag Seeder (Mode: full | Target: 4) ............................................................................................... RUNNING
  [##################################################] 100% ........................................ < 1 sec spend / < 1 sec left | Memory 30.0 MiBs
  ProductTag Seeder (Items: 4 | Per second: 33.7/s) ........................................................................................... DONE

  UserDetails Seeder (Mode: full | Target: 100) ............................................................................................ RUNNING
  [##################################################] 100% ........................................ < 1 sec spend / < 1 sec left | Memory 30.0 MiBs
  UserDetails Seeder (Items: 100 | Per second: 2,460.5/s) ..................................................................................... DONE

  Reviews Seeder (Mode: full | Target: 1000) ............................................................................................... RUNNING
  [##################################################] 100% ........................................ < 1 sec spend / < 1 sec left | Memory 32.0 MiBs
  Reviews Seeder (Items: 1,000 | Per second: 17,672.5/s) ...................................................................................... DONE

```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Note

I do not own the rights to Doom Guy or any intellectual property related to the DOOM franchise. This project is purely a reference to the WAD file format.

![Footer](/.github/footer.jpg)
