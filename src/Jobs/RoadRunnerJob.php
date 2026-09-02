<?php

namespace Ebects\RoadRunnerQueue\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * RoadRunner Retry Support Base Job
 * 
 * Extends this class untuk support Laravel-native retry mechanism di RoadRunner
 * 
 * Usage:
 * ```php
 * class MyJob extends RoadRunnerJob
 * {
 *     public $tries = 3;
 *     public $backoff = [10, 30, 60];
 *     public $timeout = 120;
 *     
 *     protected function process(): void
 *     {
 *         // Your job logic here
 *     }
 *     
 *     public function failed(\Throwable $exception): void
 *     {
 *         // Your cleanup logic (optional)
 *     }
 * }
 * ```
 */
abstract class RoadRunnerJob implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue, SerializesModels;

    /**
     * Laravel native retry configuration
     * Override these in your job class
     */
    public $tries = 1;
    public $backoff = [10, 30, 60];
    public $timeout = 0;
    public $deleteWhenMissingModels = true;

    /**
     * 🔥 FINAL handle() - cannot be overridden
     * This ensures retry logic always executes
     */
    final public function handle(): void
    {
        $jobId = $this->getJobIdentifier();
        $attemptKey = $this->getAttemptKey($jobId);

        $this->logLifecycle('debug', 'Job STARTED', [
            'job_class' => get_class($this),
            'job_id' => $jobId,
            'max_tries' => $this->tries,
            'queue' => $this->resolveQueue(),
        ]);

        if ($this->timeout > 0) {
            set_time_limit($this->timeout);
        }

        try {
            $this->process();
        } catch (\Throwable $exception) {
            $this->handleFailure($jobId, $attemptKey, $exception);

            return;
        }

        $this->forgetAttempt($attemptKey);

        $this->callHook(fn () => $this->afterSuccess(), 'afterSuccess');

        $this->logLifecycle('debug', 'Job COMPLETED', [
            'job_class' => get_class($this),
            'job_id' => $jobId,
        ]);
    }

    /**
     * Run a user-supplied lifecycle hook without letting it change the outcome
     * of a job that has already succeeded or already been marked for retry.
     */
    private function callHook(callable $hook, string $name): void
    {
        try {
            $hook();
        } catch (\Throwable $e) {
            $this->resolveLogger()->error("Error in {$name}() hook", [
                'job_class' => get_class($this),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Decide between retry and final failure.
     *
     * This method never rethrows: the job has already been consumed and any
     * further retry is driven by re-dispatch, not by the broker.
     */
    private function handleFailure(string $jobId, string $attemptKey, \Throwable $exception): void
    {
        $maxTries = max(1, (int) $this->tries);
        $attempt = $this->rememberAttempt($attemptKey);
        $canRetry = $attempt !== null && $attempt < $maxTries;

        $this->resolveLogger()->error('Job FAILED', [
            'job_class' => get_class($this),
            'job_id' => $jobId,
            'attempt' => $attempt,
            'max_tries' => $maxTries,
            'will_retry' => $canRetry,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);

        if ($canRetry) {
            $delay = $this->getRetryDelay($attempt);

            $this->callHook(fn () => $this->beforeRetry($attempt), 'beforeRetry');

            $this->retryJob($delay);

            $this->logLifecycle('info', 'Job scheduled for RETRY', [
                'job_class' => get_class($this),
                'job_id' => $jobId,
                'current_attempt' => $attempt,
                'next_attempt' => $attempt + 1,
                'max_tries' => $maxTries,
                'delay_seconds' => $delay,
                'retry_at' => now()->addSeconds($delay)->toDateTimeString(),
            ]);

            return;
        }

        // A null attempt means the counter store is unreachable. Retrying blind
        // would loop forever, so the job is failed now and stays recoverable
        // through the failed_jobs table.
        if ($attempt === null) {
            $this->resolveLogger()->warning('Attempt counter unavailable, failing job without retry', [
                'job_class' => get_class($this),
                'job_id' => $jobId,
            ]);
        }

        $this->forgetAttempt($attemptKey);

        $this->resolveLogger()->error('Job FINAL FAILURE', [
            'job_class' => get_class($this),
            'job_id' => $jobId,
            'total_attempts' => $attempt,
        ]);

        if (method_exists($this, 'failed')) {
            try {
                $this->failed($exception);
            } catch (\Throwable $failedException) {
                $this->resolveLogger()->error('Error in failed() handler', [
                    'job_class' => get_class($this),
                    'job_id' => $jobId,
                    'error' => $failedException->getMessage(),
                    'trace' => $failedException->getTraceAsString(),
                ]);
            }
        }

        $this->insertToFailedJobs($jobId, $exception);
    }

    /**
     * 🔥 ABSTRACT process() method
     * Child classes MUST implement this instead of handle()
     */
    abstract protected function process(): void;

    /**
     * Get unique job identifier
     */
    protected function getJobIdentifier(): string
    {
        $class = get_class($this);
        $properties = get_object_vars($this);
        
        // Remove framework properties
        unset(
            $properties['job'],
            $properties['connection'],
            $properties['queue'],
            $properties['chainConnection'],
            $properties['chainQueue'],
            $properties['chainCatchCallbacks'],
            $properties['delay'],
            $properties['afterCommit'],
            $properties['middleware'],
            $properties['chained']
        );
        
        // Common ID property names
        $idKeys = ['id', 'idSuratMasuk', 'idNaskah', 'userId', 'jobId', 'modelId'];
        
        foreach ($idKeys as $key) {
            if (isset($properties[$key])) {
                return "{$class}:{$properties[$key]}";
            }
        }
        
        // Fallback: hash of all properties
        return "{$class}:" . md5(serialize($properties));
    }

    /**
     * Get cache key for attempt tracking
     */
    protected function getAttemptKey(string $jobId): string
    {
        return config('roadrunner-queue.cache_prefix', 'rr_job_attempt:').$jobId;
    }

    /**
     * Increment and persist the attempt counter.
     *
     * Returns null when the cache store cannot be read or written, which the
     * caller treats as "retry budget unknown" rather than as a job failure.
     */
    private function rememberAttempt(string $attemptKey): ?int
    {
        try {
            $attempt = ((int) Cache::get($attemptKey, 0)) + 1;

            Cache::put($attemptKey, $attempt, (int) config('roadrunner-queue.attempt_ttl', 86400));

            return $attempt;
        } catch (\Throwable $e) {
            $this->resolveLogger()->warning('Unable to track job attempt', [
                'job_class' => get_class($this),
                'attempt_key' => $attemptKey,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function forgetAttempt(string $attemptKey): void
    {
        try {
            Cache::forget($attemptKey);
        } catch (\Throwable $e) {
            $this->resolveLogger()->warning('Unable to clear job attempt counter', [
                'job_class' => get_class($this),
                'attempt_key' => $attemptKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * The queue this job came from, or null when it is unknown.
     *
     * Guessing a queue name here would silently publish the retry to a pipeline
     * nobody consumes, so callers are expected to handle null explicitly.
     */
    private function resolveQueue(): ?string
    {
        return $this->queue ?: null;
    }

    private function resolveLogger(): \Psr\Log\LoggerInterface
    {
        $channel = config('roadrunner-queue.logging.channel');

        return $channel ? Log::channel($channel) : Log::getFacadeRoot();
    }

    private function logLifecycle(string $level, string $message, array $context = []): void
    {
        if (! config('roadrunner-queue.logging.enabled', true)) {
            return;
        }

        $this->resolveLogger()->log($level, $message, $context);
    }

    /**
     * Calculate retry delay based on backoff
     */
    protected function getRetryDelay(int $currentAttempt): int
    {
        $backoff = $this->backoff;
        
        // Convert single value to array
        if (is_int($backoff)) {
            $backoff = [$backoff];
        }
        
        if (!is_array($backoff)) {
            $backoff = [10, 30, 60]; // Default
        }
        
        // Get delay for current attempt (0-indexed)
        $index = $currentAttempt - 1;
        
        if (isset($backoff[$index])) {
            return $backoff[$index];
        }
        
        // Use last value if attempt exceeds array
        return end($backoff);
    }

    /**
     * Re-dispatch job with delay
     */
    protected function retryJob(int $delay): void
    {
        $newJob = unserialize(serialize($this));

        $pending = dispatch($newJob)->delay(now()->addSeconds($delay));

        if ($queue = $this->resolveQueue()) {
            $pending->onQueue($queue);
        }
    }

    /**
     * Insert to failed_jobs table
     */
    protected function insertToFailedJobs(string $jobId, \Throwable $exception): void
    {
        $connection = $this->connection ?: config('queue.default');

        try {
            DB::table('failed_jobs')->insert([
                'uuid' => (string) Str::uuid(),
                'connection' => $connection,
                'queue' => $this->resolveQueue()
                    ?? config("queue.connections.{$connection}.queue", 'default'),
                'payload' => json_encode([
                    'displayName' => get_class($this),
                    'job' => serialize($this),
                    'data' => [
                        'commandName' => get_class($this),
                        'command' => serialize($this),
                    ]
                ]),
                'exception' => (string) $exception,
                'failed_at' => now(),
            ]);
            
            $this->logLifecycle('info', 'Inserted to failed_jobs table', [
                'job_class' => get_class($this),
                'job_id' => $jobId,
            ]);

        } catch (\Throwable $e) {
            $this->resolveLogger()->error('Failed to insert to failed_jobs', [
                'job_class' => get_class($this),
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Helper: Get current attempt number
     */
    protected function currentAttempt(): int
    {
        $attemptKey = $this->getAttemptKey($this->getJobIdentifier());

        try {
            return ((int) Cache::get($attemptKey, 0)) + 1;
        } catch (\Throwable $e) {
            return 1;
        }
    }

    /**
     * Helper: Check if final attempt
     */
    protected function isFinalAttempt(): bool
    {
        return $this->currentAttempt() >= $this->tries;
    }

    /**
     * Optional: Override in child for custom logic before retry
     */
    protected function beforeRetry(int $attempt): void
    {
        // Child can override
    }

    /**
     * Optional: Override in child for custom logic after success
     */
    protected function afterSuccess(): void
    {
        // Child can override
    }
}
