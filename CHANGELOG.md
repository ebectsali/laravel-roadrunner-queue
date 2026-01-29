# Changelog

All notable changes to `laravel-roadrunner-queue` will be documented in this file.

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
