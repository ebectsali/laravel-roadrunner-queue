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
        
        // Track current attempt
        $currentAttempt = Cache::get($attemptKey, 0) + 1;
        Cache::put($attemptKey, $currentAttempt, now()->addDay());
        
        // Get max tries
        $maxTries = $this->tries;
        
        // Log job start
        Log::info('🚀 Job STARTED', [
            'job_class' => get_class($this),
            'job_id' => $jobId,
            'attempt' => $currentAttempt,
            'max_tries' => $maxTries,
            'queue' => $this->queue ?? 'default',
        ]);
        
        // Set timeout if configured
        if ($this->timeout > 0) {
            set_time_limit($this->timeout);
        }
        
        try {
            // Call child class's process() method
            $this->process();
            
            // Success! Clear attempt counter
            Cache::forget($attemptKey);
            
            Log::info('✅ Job COMPLETED', [
                'job_class' => get_class($this),
                'job_id' => $jobId,
                'attempt' => $currentAttempt,
            ]);
            
        } catch (\Throwable $exception) {
            // Log error
            Log::error('❌ Job FAILED', [
                'job_class' => get_class($this),
                'job_id' => $jobId,
                'attempt' => $currentAttempt,
                'max_tries' => $maxTries,
                'will_retry' => $currentAttempt < $maxTries,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
            
            // Check if should retry
            if ($currentAttempt < $maxTries) {
                // Calculate retry delay
                $delay = $this->getRetryDelay($currentAttempt);
                
                // Re-dispatch job with delay
                $this->retryJob($delay);
                
                Log::info('🔄 Job scheduled for RETRY', [
                    'job_class' => get_class($this),
                    'job_id' => $jobId,
                    'current_attempt' => $currentAttempt,
                    'next_attempt' => $currentAttempt + 1,
                    'max_tries' => $maxTries,
                    'delay_seconds' => $delay,
                    'retry_at' => now()->addSeconds($delay)->toDateTimeString(),
                ]);
                
            } else {
                // Max retries reached
                Cache::forget($attemptKey);
                
                Log::error('🔴 Job FINAL FAILURE (max retries reached)', [
                    'job_class' => get_class($this),
                    'job_id' => $jobId,
                    'total_attempts' => $currentAttempt,
                ]);
                
                // Call failed() method if exists
                if (method_exists($this, 'failed')) {
                    try {
                        $this->failed($exception);
                        
                        Log::info('✅ failed() handler executed', [
                            'job_class' => get_class($this),
                            'job_id' => $jobId,
                        ]);
                    } catch (\Throwable $failedException) {
                        Log::error('❌ Error in failed() handler', [
                            'job_class' => get_class($this),
                            'job_id' => $jobId,
                            'error' => $failedException->getMessage(),
                        ]);
                    }
                }
                
                // Insert to failed_jobs table
                $this->insertToFailedJobs($jobId, $exception);
            }
            
            // Always re-throw so RR knows job failed
            throw $exception;
        }
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
        return "rr_job_attempt:{$jobId}";
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
        // Create new instance with same properties
        $jobClass = get_class($this);
        $properties = get_object_vars($this);
        
        // Remove framework properties
        unset(
            $properties['job'],
            $properties['connection'],
            $properties['chainConnection'],
            $properties['chainQueue'],
            $properties['chainCatchCallbacks'],
            $properties['delay'],
            $properties['afterCommit'],
            $properties['middleware'],
            $properties['chained']
        );
        
        // Create new job instance
        $newJob = new $jobClass(...array_values($properties));
        
        // Dispatch with delay
        dispatch($newJob)
            ->onQueue($this->queue ?? 'default')
            ->delay(now()->addSeconds($delay));
    }

    /**
     * Insert to failed_jobs table
     */
    protected function insertToFailedJobs(string $jobId, \Throwable $exception): void
    {
        try {
            DB::table('failed_jobs')->insert([
                'uuid' => (string) Str::uuid(),
                'connection' => 'rabbitmq',
                'queue' => $this->queue ?? 'default',
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
            
            Log::info('📝 Inserted to failed_jobs table', [
                'job_class' => get_class($this),
                'job_id' => $jobId,
            ]);
            
        } catch (\Throwable $e) {
            Log::error('Failed to insert to failed_jobs', [
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
        $jobId = $this->getJobIdentifier();
        $attemptKey = $this->getAttemptKey($jobId);
        return Cache::get($attemptKey, 1);
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
