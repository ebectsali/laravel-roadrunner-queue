<?php

namespace Ebects\RoadRunnerQueue\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RoadRunnerFailedCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rr:failed 
                            {--queue= : Filter by queue name}
                            {--limit=20 : Number of jobs to display}
                            {--verbose : Show full exception}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all failed RoadRunner jobs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $queue = $this->option('queue');
        $limit = (int) $this->option('limit');
        $verbose = $this->option('verbose');

        // Build query
        $query = DB::table('failed_jobs')->orderBy('failed_at', 'desc');
        
        if ($queue) {
            $query->where('queue', $queue);
        }

        $totalCount = $query->count();
        $failedJobs = $query->limit($limit)->get();

        if ($failedJobs->isEmpty()) {
            $this->info('✅ No failed jobs found');
            return 0;
        }

        // Header
        $this->info("📋 Failed Jobs ({$failedJobs->count()} of {$totalCount})");
        
        if ($queue) {
            $this->info("📍 Queue: {$queue}");
        }
        
        $this->newLine();

        // Display jobs
        foreach ($failedJobs as $job) {
            $this->displayJob($job, $verbose);
            $this->newLine();
        }

        // Show more hint
        if ($totalCount > $limit) {
            $remaining = $totalCount - $limit;
            $this->comment("💡 {$remaining} more job(s) not shown. Use --limit={$totalCount} to see all.");
        }

        return 0;
    }

    /**
     * Display job information
     */
    protected function displayJob($job, bool $verbose): void
    {
        // Parse payload
        $payload = json_decode($job->payload, true);
        $jobClass = $payload['displayName'] ?? 'Unknown';
        
        // Get first line of exception
        $exceptionLines = explode("\n", $job->exception);
        $exceptionMessage = $exceptionLines[0] ?? 'Unknown error';

        // Basic info table
        $this->table(
            ['Field', 'Value'],
            [
                ['UUID', $job->uuid],
                ['ID', $job->id ?? 'N/A'],
                ['Job Class', $jobClass],
                ['Queue', $job->queue],
                ['Connection', $job->connection],
                ['Failed At', $job->failed_at],
                ['Exception', $exceptionMessage],
            ]
        );

        // Show full exception if verbose
        if ($verbose) {
            $this->line('Full Exception:');
            $this->line('<fg=red>' . $job->exception . '</>');
        }

        // Show retry command hint
        $this->comment("💡 Retry: php artisan rr:retry {$job->uuid}");
    }
}
