<?php

namespace Ebects\RoadRunnerQueue\Tests\Fixtures;

use Ebects\RoadRunnerQueue\Jobs\RoadRunnerJob;

class TestJob extends RoadRunnerJob
{
    public static int $processed = 0;

    public static int $failedCalls = 0;

    public static bool $shouldThrow = false;

    public int $id = 7;

    public static function reset(): void
    {
        static::$processed = 0;
        static::$failedCalls = 0;
        static::$shouldThrow = false;
    }

    protected function process(): void
    {
        static::$processed++;

        if (static::$shouldThrow) {
            throw new \RuntimeException('boom');
        }
    }

    public function failed(\Throwable $exception): void
    {
        static::$failedCalls++;
    }
}
