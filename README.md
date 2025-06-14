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

![showcase](/.github/showcase.jpg)

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

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

![Footer](/.github/footer.jpg)
