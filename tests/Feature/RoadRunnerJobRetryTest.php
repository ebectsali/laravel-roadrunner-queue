<?php

namespace Ebects\RoadRunnerQueue\Tests\Feature;

use Ebects\RoadRunnerQueue\Tests\Fixtures\TestJob;
use Ebects\RoadRunnerQueue\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class RoadRunnerJobRetryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        TestJob::reset();
        Queue::fake();
    }

    private function killCache(): void
    {
        Cache::swap(new class
        {
            public function __call($method, $arguments)
            {
                throw new \RuntimeException('cache is down');
            }
        });
    }

    public function test_successful_job_does_not_schedule_a_retry(): void
    {
        (new TestJob)->handle();

        $this->assertSame(1, TestJob::$processed);
        Queue::assertNothingPushed();
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_successful_job_survives_an_unreachable_cache(): void
    {
        $this->killCache();

        (new TestJob)->handle();

        $this->assertSame(1, TestJob::$processed);
        Queue::assertNothingPushed();
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_failing_job_is_retried_until_tries_is_exhausted(): void
    {
        TestJob::$shouldThrow = true;

        $job = new TestJob;
        $job->tries = 3;
        $job->onQueue('jurnal');

        $job->handle();

        Queue::assertPushed(TestJob::class, 1);
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(0, TestJob::$failedCalls);
    }

    public function test_last_attempt_fails_the_job_exactly_once(): void
    {
        TestJob::$shouldThrow = true;

        for ($i = 0; $i < 3; $i++) {
            $job = new TestJob;
            $job->tries = 3;
            $job->onQueue('jurnal');
            $job->handle();
        }

        Queue::assertPushed(TestJob::class, 2);
        $this->assertSame(1, TestJob::$failedCalls);
        $this->assertSame(1, DB::table('failed_jobs')->count());
    }

    public function test_handle_never_rethrows_so_the_broker_cannot_requeue(): void
    {
        TestJob::$shouldThrow = true;

        $job = new TestJob;
        $job->tries = 1;

        $job->handle();

        $this->assertSame(1, DB::table('failed_jobs')->count());
    }

    public function test_failing_job_with_unreachable_cache_is_recorded_instead_of_looping(): void
    {
        TestJob::$shouldThrow = true;
        $this->killCache();

        $job = new TestJob;
        $job->tries = 3;

        $job->handle();

        Queue::assertNothingPushed();
        $this->assertSame(1, TestJob::$failedCalls);
        $this->assertSame(1, DB::table('failed_jobs')->count());
    }

    public function test_retry_keeps_the_original_queue(): void
    {
        TestJob::$shouldThrow = true;

        $job = new TestJob;
        $job->tries = 2;
        $job->onQueue('jurnal');

        $job->handle();

        Queue::assertPushedOn('jurnal', TestJob::class);
    }

    public function test_retry_without_a_queue_is_not_pushed_to_a_guessed_default(): void
    {
        TestJob::$shouldThrow = true;

        $job = new TestJob;
        $job->tries = 2;

        $job->handle();

        Queue::assertPushed(TestJob::class, function (TestJob $pushed) {
            return $pushed->queue === null;
        });
    }

    public function test_failed_jobs_row_uses_the_configured_connection(): void
    {
        TestJob::$shouldThrow = true;

        $job = new TestJob;
        $job->tries = 1;

        $job->handle();

        $row = DB::table('failed_jobs')->first();

        $this->assertSame('redis', $row->connection);
        $this->assertSame('connection-default', $row->queue);
    }

    public function test_attempt_counter_uses_the_configured_prefix(): void
    {
        config()->set('roadrunner-queue.cache_prefix', 'zz_prefix:');
        TestJob::$shouldThrow = true;

        $job = new TestJob;
        $job->tries = 3;

        $job->handle();

        $this->assertSame(1, Cache::get('zz_prefix:'.TestJob::class.':7'));
    }
}
