<?php

namespace Ebects\RoadRunnerQueue\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class RoadRunnerRetryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rr:retry 
                            {id=all : The UUID of failed job or "all" to retry all jobs}
                            {--id=* : Specific UUIDs to retry (can specify multiple)}
                            {--queue= : Only retry jobs from this queue}
                            {--range= : Range of IDs to retry (e.g., "1-5")}
                            {--force : Skip confirmation}
                            {--reset-attempts : Reset attempt counter before retry}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry failed RoadRunner jobs from failed_jobs table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $id = $this->argument('id');
        $ids = $this->option('id');
        $queue = $this->option('queue');
        $range = $this->option('range');
        $force = $this->option('force');
        $resetAttempts = $this->option('reset-attempts');

        $this->info('🔄 RoadRunner Job Retry Tool');
        $this->newLine();

        // Get failed jobs
        $failedJobs = $this->getFailedJobs($id, $ids, $queue, $range);

        if ($failedJobs->isEmpty()) {
            $this->warn('⚠️  No failed jobs found matching criteria.');
            return 0;
        }

        // Show summary
        $this->showSummary($failedJobs, $queue, $resetAttempts);

        // Confirm
        if (!$force && !$this->confirm("Retry {$failedJobs->count()} job(s)?", true)) {
            $this->info('❌ Cancelled.');
            return 0;
        }

        // Retry jobs
        $this->retryJobs($failedJobs, $resetAttempts);

        return 0;
    }

    /**
     * Get failed jobs based on criteria
     */
    protected function getFailedJobs($id, $ids, $queue, $range)
    {
        $query = DB::table('failed_jobs');

        // Multiple specific IDs via --id option
        if (!empty($ids)) {
            $this->info('📋 Fetching jobs by UUIDs...');
            $query->whereIn('uuid', $ids);
            return $query->get();
        }

        // Single ID or "all"
        if ($id !== 'all') {
            $this->info("📋 Fetching job: {$id}");
            $query->where('uuid', $id);
            return $query->get();
        }

        // Range of IDs
        if ($range) {
            $this->info("📋 Fetching jobs in range: {$range}");
            
            if (preg_match('/^(\d+)-(\d+)$/', $range, $matches)) {
                $start = (int) $matches[1];
                $end = (int) $matches[2];
                $query->whereBetween('id', [$start, $end]);
            } else {
                $this->error('❌ Invalid range format. Use: 1-5');
                return collect();
            }
        }

        // All jobs (with optional queue filter)
        $this->info('📋 Fetching all failed jobs...');
        
        if ($queue) {
            $this->info("   Filtered by queue: {$queue}");
            $query->where('queue', $queue);
        }

        return $query->orderBy('failed_at', 'desc')->get();
    }

    /**
     * Show summary
     */
    protected function showSummary($failedJobs, $queue, $resetAttempts)
    {
        $this->newLine();
        $this->info('📊 Summary:');
        $this->line("   Total jobs: {$failedJobs->count()}");
        
        if ($queue) {
            $this->line("   Queue filter: {$queue}");
        }
        
        if ($resetAttempts) {
            $this->warn('   ⚠️  Attempt counters will be RESET');
        }
        
        // Group by queue
        $groupedByQueue = $failedJobs->groupBy('queue');
        
        $this->newLine();
        $this->info('   By Queue:');
        foreach ($groupedByQueue as $queueName => $jobs) {
            $this->line("      • {$queueName}: {$jobs->count()} job(s)");
        }
        
        // Group by job class
        $groupedByClass = $failedJobs->map(function ($job) {
            $payload = json_decode($job->payload, true);
            return $payload['displayName'] ?? 'Unknown';
        })->groupBy(function ($item) {
            return class_basename($item);
        });
        
        $this->newLine();
        $this->info('   By Job Class:');
        foreach ($groupedByClass as $class => $items) {
            $this->line("      • {$class}: {$items->count()} job(s)");
        }
        
        // Show detailed table if <= 10 jobs
        if ($failedJobs->count() <= 10) {
            $this->newLine();
            $this->table(
                ['UUID', 'Job Class', 'Queue', 'Failed At'],
                $failedJobs->map(function ($job) {
                    $payload = json_decode($job->payload, true);
                    $jobClass = $payload['displayName'] ?? 'Unknown';
                    
                    return [
                        substr($job->uuid, 0, 8) . '...',
                        class_basename($jobClass),
                        $job->queue,
                        \Carbon\Carbon::parse($job->failed_at)->diffForHumans(),
                    ];
                })->toArray()
            );
        }
        
        $this->newLine();
    }

    /**
     * Retry jobs
     */
    protected function retryJobs($failedJobs, $resetAttempts)
    {
        $this->info('🚀 Retrying jobs...');
        $this->newLine();

        $progressBar = $this->output->createProgressBar($failedJobs->count());
        $progressBar->start();

        $success = 0;
        $failed = 0;
        $errors = [];

        foreach ($failedJobs as $failedJob) {
            try {
                $this->retryJob($failedJob, $resetAttempts);
                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = [
                    'uuid' => $failedJob->uuid,
                    'error' => $e->getMessage(),
                ];
                
                Log::error('Failed to retry job', [
                    'uuid' => $failedJob->uuid,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Show results
        $this->info('📈 Results:');
        
        if ($success > 0) {
            $this->info("   ✅ Successfully retried: {$success} job(s)");
        }

        if ($failed > 0) {
            $this->error("   ❌ Failed to retry: {$failed} job(s)");
            
            if (!empty($errors)) {
                $this->newLine();
                $this->error('   Errors:');
                foreach ($errors as $error) {
                    $this->line("      • " . substr($error['uuid'], 0, 8) . "...: {$error['error']}");
                }
            }
        }

        $this->newLine();
        $this->info('✨ Retry process completed!');
    }

    /**
     * Retry a single job
     */
    protected function retryJob($failedJob, $resetAttempts)
    {
        // Decode payload
        $payload = json_decode($failedJob->payload, true);
        
        if (!$payload) {
            throw new \Exception("Invalid payload");
        }

        // Extract job
        $job = $this->extractJob($payload);
        
        if (!$job) {
            throw new \Exception("Cannot extract job from payload");
        }

        // Reset attempt counter if requested
        if ($resetAttempts) {
            $this->resetAttemptCounter($job);
        }

        // Dispatch job
        $queue = $failedJob->queue ?: 'default';
        dispatch($job)->onQueue($queue);

        // Delete from failed_jobs
        DB::table('failed_jobs')
            ->where('uuid', $failedJob->uuid)
            ->delete();

        // Log
        Log::info('Job retried', [
            'uuid' => $failedJob->uuid,
            'queue' => $queue,
            'job_class' => get_class($job),
            'reset_attempts' => $resetAttempts,
        ]);
    }

    /**
     * Extract job from payload
     */
    protected function extractJob($payload)
    {
        // Try different payload structures
        $attempts = [
            $payload['data']['command'] ?? null,
            $payload['data']['job'] ?? null,
            $payload['job'] ?? null,
            $payload['command'] ?? null,
        ];

        foreach ($attempts as $serialized) {
            if ($serialized) {
                try {
                    $job = unserialize($serialized);
                    if (is_object($job)) {
                        return $job;
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Reset attempt counter
     */
    protected function resetAttemptCounter($job)
    {
        $jobClass = get_class($job);
        $properties = get_object_vars($job);
        
        // Remove framework properties
        $frameworkProps = [
            'job', 'connection', 'queue', 'chainConnection',
            'chainQueue', 'chainCatchCallbacks', 'delay',
            'afterCommit', 'middleware', 'chained'
        ];
        
        foreach ($frameworkProps as $prop) {
            unset($properties[$prop]);
        }
        
        // Find ID
        $idKeys = ['id', 'idSuratMasuk', 'idNaskah', 'userId', 'jobId', 'modelId'];
        
        $jobId = null;
        foreach ($idKeys as $key) {
            if (isset($properties[$key])) {
                $jobId = "{$jobClass}:{$properties[$key]}";
                break;
            }
        }
        
        if (!$jobId) {
            $jobId = "{$jobClass}:" . md5(serialize($properties));
        }
        
        // Clear cache
        $attemptKey = "rr_job_attempt:{$jobId}";
        Cache::forget($attemptKey);
    }
}
