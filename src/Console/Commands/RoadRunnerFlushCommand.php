<?php

namespace Ebects\RoadRunnerQueue\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class RoadRunnerFlushCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rr:flush 
                            {--queue= : Only flush jobs from specific queue}
                            {--hours= : Only flush jobs older than X hours}
                            {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete all failed RoadRunner jobs from the failed_jobs table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $queue = $this->option('queue');
        $hours = $this->option('hours');
        $force = $this->option('force');

        $this->info('🗑️  RoadRunner Failed Jobs Flush');
        $this->newLine();

        // Build query
        $query = DB::table('failed_jobs');
        
        if ($queue) {
            $query->where('queue', $queue);
        }
        
        if ($hours) {
            $query->where('failed_at', '<', now()->subHours($hours));
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info('✅ No failed jobs to flush.');
            return 0;
        }

        // Show what will be deleted
        $this->warn("⚠️  This will DELETE {$count} failed job(s)!");
        
        if ($queue) {
            $this->line("   Queue filter: {$queue}");
        }
        
        if ($hours) {
            $this->line("   Older than: {$hours} hours");
        }
        
        $this->newLine();

        // Confirm
        if (!$force) {
            if (!$this->confirm('Are you sure you want to delete these jobs?')) {
                $this->info('❌ Cancelled.');
                return 0;
            }
        }

        // Delete
        $deleted = $query->delete();

        // Clear all attempt counters (best effort)
        $this->clearAttemptCounters();

        $this->info("✅ Deleted {$deleted} failed job(s)");

        return 0;
    }

    /**
     * Clear attempt counters from cache
     */
    protected function clearAttemptCounters()
    {
        try {
            // Get all cache keys matching pattern
            $keys = Cache::getRedis()->keys('rr_job_attempt:*');
            
            if (!empty($keys)) {
                foreach ($keys as $key) {
                    // Remove prefix if exists
                    $key = str_replace(config('database.redis.options.prefix', ''), '', $key);
                    Cache::forget($key);
                }
                
                $this->line("   Cleared " . count($keys) . " attempt counter(s)");
            }
        } catch (\Throwable $e) {
            // Silently fail - not critical
        }
    }
}
