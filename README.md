# 🚀 Laravel RoadRunner Queue

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ebects/laravel-roadrunner-queue.svg?style=flat-square)](https://packagist.org/packages/ebects/laravel-roadrunner-queue)
[![Total Downloads](https://img.shields.io/packagist/dt/ebects/laravel-roadrunner-queue.svg?style=flat-square)](https://packagist.org/packages/ebects/laravel-roadrunner-queue)
[![PHP Version](https://img.shields.io/packagist/php-v/ebects/laravel-roadrunner-queue.svg?style=flat-square)](https://packagist.org/packages/ebects/laravel-roadrunner-queue)
[![License](https://img.shields.io/packagist/l/ebects/laravel-roadrunner-queue.svg?style=flat-square)](https://packagist.org/packages/ebects/laravel-roadrunner-queue)

**Laravel native queue retry mechanism for RoadRunner** - Get the best of both worlds: RoadRunner's performance with Laravel's elegant retry system!

## 🎯 The Problem

RoadRunner is **amazing** for Laravel Octane, but it doesn't support Laravel's native queue retry mechanism:
- ❌ `$tries` property ignored
- ❌ `$backoff` doesn't work
- ❌ `failed()` method never called
- ❌ No automatic retry on failure
- ❌ Jobs disappear without trace

This forces you to choose:
- **Laravel Queue Worker** → ✅ Retry support but ❌ zombie processes 🧟
- **RoadRunner** → ✅ No zombies but ❌ no retry support 😢

## ✨ The Solution

This package gives you **BOTH**:
- ✅ RoadRunner's performance & stability (no zombie processes!)
- ✅ Laravel's native retry mechanism (`$tries`, `$backoff`, `failed()`)
- ✅ Automatic retry with exponential backoff
- ✅ Failed job tracking & management
- ✅ Artisan commands for job management

## 📦 Installation

```bash
composer require ebects/laravel-roadrunner-queue
```

Publish config (optional):
```bash
php artisan vendor:publish --tag=roadrunner-queue-config
```

## 🚀 Quick Start

### Step 1: Extend `RoadRunnerJob`

Instead of implementing `ShouldQueue`, extend the base class:

```php
<?php

namespace App\Jobs;

use Elects\RoadRunnerQueue\Jobs\RoadRunnerJob;

class ProcessInvoice extends RoadRunnerJob
{
    // ✅ NOW WORKS in RoadRunner!
    public $tries = 3;
    public $backoff = [10, 30, 60];
    public $timeout = 120;
    
    public $invoiceId;

    public function __construct($invoiceId)
    {
        $this->invoiceId = $invoiceId;
    }

    // ✅ Implement process() instead of handle()
    protected function process(): void
    {
        $invoice = Invoice::find($this->invoiceId);
        $invoice->process();
    }

    // ✅ Auto-called after max retries!
    public function failed(\Throwable $exception): void
    {
        $invoice = Invoice::find($this->invoiceId);
        $invoice->markAsFailed();
    }
}
```

### Step 2: Dispatch as Normal

```php
ProcessInvoice::dispatch($invoiceId);
```

That's it! 🎉

## 🎮 Features

### 1. **Automatic Retry with Backoff**

```php
class MyJob extends RoadRunnerJob
{
    public $tries = 5;
    public $backoff = [10, 30, 60, 120, 300]; // seconds
    
    protected function process(): void
    {
        // Your code - automatically retries on exception!
    }
}
```

**What happens:**
```
Attempt 1: Fails → Wait 10s
Attempt 2: Fails → Wait 30s
Attempt 3: Fails → Wait 60s
Attempt 4: Fails → Wait 120s
Attempt 5: Fails → Call failed() → Insert to failed_jobs
```

### 2. **Failed Job Handler**

```php
protected function process(): void
{
    // Your business logic
    $this->sendEmail();
}

public function failed(\Throwable $exception): void
{
    // ✅ Automatically called after max retries!
    Log::error('Job failed completely', [
        'exception' => $exception->getMessage()
    ]);
    
    // Cleanup, notifications, rollback, etc.
    $this->cleanup();
}
```

### 3. **Helper Methods**

```php
protected function process(): void
{
    // Get current attempt number
    $attempt = $this->currentAttempt(); // 1, 2, 3, ...
    
    // Check if final attempt
    if ($this->isFinalAttempt()) {
        $this->notifyAdmin('Last attempt!');
    }
    
    // Your logic
}
```

### 4. **Artisan Commands**

Manage failed jobs like Laravel native queue:

```bash
# List failed jobs
php artisan rr:failed

# Retry all failed jobs
php artisan rr:retry all

# Retry specific job
php artisan rr:retry {uuid}

# Retry by queue
php artisan rr:retry all --queue=emails

# Retry with fresh attempt counter
php artisan rr:retry all --reset-attempts

# Delete specific job
php artisan rr:forget {uuid}

# Delete all failed jobs
php artisan rr:flush

# Delete old jobs
php artisan rr:flush --hours=24
```

## 📖 Documentation

### Configuration

The package works out of the box, but you can customize:

```php
// config/roadrunner-queue.php

return [
    'cache_driver' => 'redis',
    'attempt_ttl' => 86400,
    'cache_prefix' => 'rr_job_attempt:',
    'default_queue' => 'default',
    'logging' => [
        'enabled' => true,
        'channel' => null,
    ],
];
```

### Advanced Usage

#### Custom Backoff Logic

```php
class MyJob extends RoadRunnerJob
{
    protected function getRetryDelay(int $currentAttempt): int
    {
        // Exponential backoff: 2^attempt * 10
        return pow(2, $currentAttempt) * 10;
    }
}
```

#### Conditional Retry

```php
protected function process(): void
{
    try {
        $this->sendEmail();
    } catch (RateLimitException $e) {
        // Retry for rate limits
        throw $e;
    } catch (InvalidEmailException $e) {
        // Don't retry for invalid data
        $this->failed($e);
        return;
    }
}
```

#### Custom Job Identifier

```php
protected function getJobIdentifier(): string
{
    return "CustomJob:{$this->userId}:{$this->type}";
}
```

## 🆚 Comparison

### Before (Native RoadRunner)

```php
class MyJob implements ShouldQueue
{
    public $tries = 3; // ❌ Ignored
    public $backoff = [10, 30, 60]; // ❌ Ignored
    
    public function handle(): void
    {
        // ❌ Fails once = job gone forever
        $this->doWork();
    }
    
    public function failed(\Throwable $e): void
    {
        // ❌ Never called
    }
}
```

### After (With Package)

```php
class MyJob extends RoadRunnerJob
{
    public $tries = 3; // ✅ Works!
    public $backoff = [10, 30, 60]; // ✅ Works!
    
    protected function process(): void
    {
        // ✅ Auto retry with backoff
        $this->doWork();
    }
    
    public function failed(\Throwable $e): void
    {
        // ✅ Auto called after 3 attempts!
    }
}
```

## 🎯 Use Cases

### API Integration with Rate Limits

```php
class CallExternalAPIJob extends RoadRunnerJob
{
    public $tries = 5;
    public $backoff = [30, 60, 120, 300, 600];
    public $timeout = 30;
    
    protected function process(): void
    {
        $response = Http::timeout($this->timeout)
            ->post('https://api.example.com/endpoint', $this->data);
            
        if ($response->failed()) {
            throw new \Exception('API call failed');
        }
    }
    
    public function failed(\Throwable $e): void
    {
        Notification::send('API integration failed after 5 attempts');
    }
}
```

### Database Operations with Deadlock Handling

```php
class UpdateInventoryJob extends RoadRunnerJob
{
    public $tries = 3;
    public $backoff = [5, 15, 30];
    
    protected function process(): void
    {
        DB::transaction(function () {
            $product = Product::lockForUpdate()->find($this->productId);
            $product->decrement('stock', $this->quantity);
        });
    }
    
    public function failed(\Throwable $e): void
    {
        // Rollback order if inventory update fails
        Order::find($this->orderId)->cancel();
    }
}
```

### Email Queue with Exponential Backoff

```php
class SendEmailJob extends RoadRunnerJob
{
    public $tries = 4;
    public $backoff = [10, 60, 300, 3600]; // 10s, 1m, 5m, 1h
    
    protected function process(): void
    {
        Mail::to($this->user)->send(new WelcomeEmail());
    }
}
```

## 🔧 Requirements

- PHP 8.1 or higher
- Laravel 10.x or 11.x
- RoadRunner 2.x or higher
- Redis (or any Laravel cache driver)

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

### Running Tests

```bash
composer test
```

### Code Style

```bash
composer format
```

## 📝 Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## 🔒 Security

If you discover any security related issues, please email security@example.com instead of using the issue tracker.

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## 🙏 Credits

- [Alee Khabib](https://github.com/yourusername)
- Inspired by the need for RoadRunner + Laravel Queue harmony
- All contributors who helped make this package better

## ⭐ Show Your Support

If this package helped you, please consider:
- Giving it a ⭐ on GitHub
- Sharing it with your team
- Contributing improvements

---

**Made with ❤️ for the Laravel & RoadRunner community**

**Keywords:** laravel, roadrunner, queue, jobs, retry, octane, rabbitmq, redis, async, background-jobs
