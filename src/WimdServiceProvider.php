<?php

namespace Wimd;

use Illuminate\Support\ServiceProvider;
use Wimd\Console\Commands\WimdSeedCommand;

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
