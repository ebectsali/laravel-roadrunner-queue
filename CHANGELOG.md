# Changelog

All notable changes to `laravel-roadrunner-queue` will be documented in this file.

## [1.1.0] - 2026-09-02

### Fixed
- 🐛 **CRITICAL:** A job whose work succeeded could still be lost. The attempt counter was written before `process()` ran and `handle()` always re-threw, so a cache outage turned a completed job into a broker-level failure. Counter bookkeeping now happens only on the failure path and cannot abort a successful job.
- 🐛 **CRITICAL:** `handle()` no longer re-throws after scheduling a retry or after writing to `failed_jobs`. The old behaviour made the broker requeue the same message the package had just re-dispatched, producing duplicate executions and — with `requeue_on_fail: true` — an unbounded loop.
- 🐛 An unreachable cache no longer results in blind retrying. When the attempt counter cannot be read, the job is failed once and recorded in `failed_jobs` instead of looping forever.
- 🐛 Retries and `failed_jobs` rows no longer fall back to a hardcoded `'default'` queue. A job with no queue is re-dispatched to the connection's own default rather than to a pipeline nobody consumes.
- 🐛 `failed_jobs.connection` is no longer hardcoded to `rabbitmq`; it uses the job's connection, falling back to `queue.default`.

### Changed
- ⚙️ `cache_prefix`, `attempt_ttl`, `logging.enabled` and `logging.channel` from `config/roadrunner-queue.php` are now actually read. Defaults are unchanged, so behaviour only differs if the config was already customised.
- 📉 Per-job `STARTED`/`COMPLETED` logs dropped from `info` to `debug` and are gated behind `logging.enabled`. Failure logs remain unconditional.
- 🪝 `beforeRetry()` and `afterSuccess()` are now actually invoked. They were previously declared but never called.

### Upgrade notes
- Dropping the re-throw changes **when the broker acks a message**: a failing job is now acked by the consumer instead of being nacked. Validate on staging before production, especially if you rely on `requeue_on_fail`.

## [1.0.1] - 2026-01-30

### Fixed
- 🐛 **CRITICAL:** Fixed job ID changing on retry due to incorrect parameter reconstruction in `retryJob()` method
- Changed from `array_values()` reconstruction to `serialize/unserialize` for exact object copy
- Job properties now correctly preserved across retries

### Impact
- Job retry now works correctly with same job ID across all attempts
- Attempt counter no longer resets
- All job properties maintained during retry

## [1.0.0] - 2026-01-29

### Added
- 🎉 Initial release
- ✨ RoadRunnerJob base class for Laravel native retry support
- ⚡ Automatic retry mechanism with configurable backoff
- 🎯 Failed job handler that actually works in RoadRunner
- 📊 Comprehensive logging of job attempts and failures
- 🛠️ Artisan commands for managing failed jobs:
  - `rr:retry` - Retry failed jobs
  - `rr:failed` - List failed jobs
  - `rr:forget` - Delete specific failed job
  - `rr:flush` - Flush all failed jobs
- 📝 Helper methods: `currentAttempt()`, `isFinalAttempt()`
- ⚙️ Configurable cache driver, TTL, and logging
- 📖 Complete documentation with examples
- 🧪 Test suite with PHPUnit
- 🔄 Automatic insertion to `failed_jobs` table after max retries
- 💾 Redis-based attempt counter tracking

### Features
- Support for Laravel 10.x and 11.x
- PHP 8.1, 8.2, and 8.3 compatibility
- Zero configuration required (works out of the box)
- Optional configuration for advanced use cases
- Service provider auto-discovery
- PSR-4 autoloading

### Documentation
- Comprehensive README with examples
- Configuration guide
- Command usage guide
- Migration guide from standard jobs
- Troubleshooting section
