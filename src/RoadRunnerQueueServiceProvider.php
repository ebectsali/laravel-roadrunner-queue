<?php

namespace Ebects\RoadRunnerQueue;

use Ebects\RoadRunnerQueue\Console\Commands\RoadRunnerFailedCommand;
use Ebects\RoadRunnerQueue\Console\Commands\RoadRunnerFlushCommand;
use Ebects\RoadRunnerQueue\Console\Commands\RoadRunnerForgetCommand;
use Ebects\RoadRunnerQueue\Console\Commands\RoadRunnerRetryCommand;
use Illuminate\Support\ServiceProvider;

class RoadRunnerQueueServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/roadrunner-queue.php',
            'roadrunner-queue'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../config/roadrunner-queue.php' => config_path('roadrunner-queue.php'),
        ], 'roadrunner-queue-config');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                RoadRunnerRetryCommand::class,
                RoadRunnerFailedCommand::class,
                RoadRunnerForgetCommand::class,
                RoadRunnerFlushCommand::class,
            ]);
        }
    }
}
