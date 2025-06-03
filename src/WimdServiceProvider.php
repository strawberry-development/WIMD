<?php

namespace Wimd;

use Illuminate\Support\ServiceProvider;
use Wimd\Console\Commands\WimdSeedCommand;

/**
 * WimdServiceProvider
 *
 * Registers and boots the WIMD (Where Is My Data) package within a Laravel application.
 * Handles configuration merging, service binding for the WimdManager, and console command registration.
 */
class WimdServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/wimd.php', 'wimd'
        );

        $this->app->singleton('wimd', function ($app) {
            return new WimdManager($app);
        });
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/wimd.php' => config_path('wimd.php'),
        ], 'wimd-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                WimdSeedCommand::class,
            ]);
        }
    }
}
