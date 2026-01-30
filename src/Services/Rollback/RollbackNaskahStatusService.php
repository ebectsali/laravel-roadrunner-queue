<?php

namespace Ebects\RoadRunnerQueue\Services\Rollback;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Rollback Naskah Status Service
 * 
 * Handles rollback operations for naskah (document) status in case of job failure
 */
class RollbackNaskahStatusService
{
    /**
     * Simple rollback - just reset status
     */
    public function simpleRollback(int $idNaskah): bool
    {
        try {
            DB::connection(config('roadrunner-queue.db_connection', 'pgsql'))
                ->table('naskah')
                ->where('id', $idNaskah)
                ->update([
                    'status' => 0,
                    'updated_at' => now(),
                ]);

            Log::info('Simple rollback naskah status', [
                'id_naskah' => $idNaskah,
                'new_status' => 0,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to rollback naskah status', [
                'id_naskah' => $idNaskah,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Rollback with specific status
     */
    public function rollbackToStatus(int $idNaskah, int $status): bool
    {
        try {
            DB::connection(config('roadrunner-queue.db_connection', 'pgsql'))
                ->table('naskah')
                ->where('id', $idNaskah)
                ->update([
                    'status' => $status,
                    'updated_at' => now(),
                ]);

            Log::info('Rollback naskah to specific status', [
                'id_naskah' => $idNaskah,
                'status' => $status,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to rollback naskah to status', [
                'id_naskah' => $idNaskah,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Complete rollback with additional data reset
     */
    public function completeRollback(int $idNaskah, array $resetData = []): bool
    {
        try {
            $updateData = array_merge([
                'status' => 0,
                'updated_at' => now(),
            ], $resetData);

            DB::connection(config('roadrunner-queue.db_connection', 'pgsql'))
                ->table('naskah')
                ->where('id', $idNaskah)
                ->update($updateData);

            Log::info('Complete rollback naskah', [
                'id_naskah' => $idNaskah,
                'reset_fields' => array_keys($updateData),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed complete rollback naskah', [
                'id_naskah' => $idNaskah,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Rollback with history tracking
     */
    public function rollbackWithHistory(int $idNaskah, string $reason = ''): bool
    {
        try {
            DB::beginTransaction();

            // Get current status before rollback
            $naskah = DB::connection(config('roadrunner-queue.db_connection', 'pgsql'))
                ->table('naskah')
                ->where('id', $idNaskah)
                ->first();

            if (!$naskah) {
                throw new \Exception('Naskah not found');
            }

            // Rollback status
            DB::connection(config('roadrunner-queue.db_connection', 'pgsql'))
                ->table('naskah')
                ->where('id', $idNaskah)
                ->update([
                    'status' => 0,
                    'updated_at' => now(),
                ]);

            // Log history (optional - if history table exists)
            if (config('roadrunner-queue.track_rollback_history', false)) {
                DB::connection(config('roadrunner-queue.db_connection', 'pgsql'))
                    ->table('naskah_rollback_history')
                    ->insert([
                        'id_naskah' => $idNaskah,
                        'previous_status' => $naskah->status,
                        'new_status' => 0,
                        'reason' => $reason,
                        'created_at' => now(),
                    ]);
            }

            DB::commit();

            Log::info('Rollback naskah with history', [
                'id_naskah' => $idNaskah,
                'previous_status' => $naskah->status,
                'reason' => $reason,
            ]);

            return true;
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed rollback naskah with history', [
                'id_naskah' => $idNaskah,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
