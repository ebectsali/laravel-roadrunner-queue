<?php

namespace Ebects\RoadRunnerQueue\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class RoadRunnerForgetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rr:forget 
                            {id : The ID or UUID of the failed job to delete}
                            {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a failed RoadRunner job from the failed jobs table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $id = $this->argument('id');
        $force = $this->option('force');

        // Find job
        $failedJob = DB::table('failed_jobs')
            ->where('uuid', $id)
            ->orWhere('id', $id)
            ->first();

        if (!$failedJob) {
            $this->error("❌ Failed job not found: {$id}");
            return 1;
        }

        // Show job info
        $payload = json_decode($failedJob->payload, true);
        $jobClass = $payload['displayName'] ?? 'Unknown';

        $this->info('📋 Job to delete:');
        $this->table(
            ['Field', 'Value'],
            [
                ['UUID', $failedJob->uuid],
                ['Job Class', $jobClass],
                ['Queue', $failedJob->queue],
                ['Failed At', $failedJob->failed_at],
            ]
        );

        // Confirm
        if (!$force) {
            if (!$this->confirm('Are you sure you want to delete this failed job?')) {
                $this->info('Cancelled');
                return 0;
            }
        }

        // Delete from database
        DB::table('failed_jobs')
            ->where('uuid', $failedJob->uuid)
            ->delete();

        // Try to clear attempt counter
        try {
            $command = unserialize($payload['data']['command']);
            $this->clearAttemptCounter($command, $jobClass);
        } catch (\Throwable $e) {
            // Silently fail - not critical
        }

        $this->info('✅ Failed job deleted successfully');

        return 0;
    }

    /**
     * Clear attempt counter from cache
     */
    protected function clearAttemptCounter($command, string $jobClass): void
    {
        $properties = get_object_vars($command);
        
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
        
        $attemptKey = "rr_job_attempt:{$jobId}";
        Cache::forget($attemptKey);
        
        $this->line("  🔄 Cleared attempt counter");
    }
}
