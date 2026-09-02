<?php

namespace Ebects\RoadRunnerQueue\Tests;

use Ebects\RoadRunnerQueue\RoadRunnerQueueServiceProvider;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [RoadRunnerQueueServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'redis');
        $app['config']->set('queue.connections.redis', [
            'driver' => 'redis',
            'queue' => 'connection-default',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('failed_jobs', function ($table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }
}
