# 🎯 Complete Package Organization Guide

Step-by-step guide untuk organize semua files dan publish package!

---

## 📁 **Step 1: Create Package Directory**

```bash
# Create new directory
mkdir laravel-roadrunner-queue
cd laravel-roadrunner-queue

# Initialize git
git init
```

---

## 📂 **Step 2: Copy All Files**

### **Root Files**

```bash
# Copy dari files yang sudah dibuat:
├── .gitignore
├── .github/
│   └── workflows/
│       └── tests.yml
├── CHANGELOG.md
├── composer.json
├── LICENSE.md
├── phpunit.xml.dist
├── PUBLISHING_GUIDE.md
└── README.md
```

### **Config Directory**

```bash
mkdir -p config
# Copy:
└── config/
    └── roadrunner-queue.php
```

### **Source Directory**

```bash
mkdir -p src/Console/Commands
mkdir -p src/Jobs

# Copy semua PHP files:
└── src/
    ├── Console/
    │   └── Commands/
    │       ├── RoadRunnerRetryCommand.php
    │       ├── RoadRunnerFailedCommand.php
    │       ├── RoadRunnerForgetCommand.php
    │       └── RoadRunnerFlushCommand.php
    ├── Jobs/
    │   └── RoadRunnerJob.php
    └── RoadRunnerQueueServiceProvider.php
```

### **Tests Directory**

```bash
mkdir -p tests/Feature
mkdir -p tests/Unit

# Create test files (see below)
```

---

## 🧪 **Step 3: Create Test Files**

### **tests/Unit/RoadRunnerJobTest.php**

```php
<?php

namespace Elects\RoadRunnerQueue\Tests\Unit;

use Orchestra\Testbench\TestCase;

class RoadRunnerJobTest extends TestCase
{
    /** @test */
    public function it_can_track_attempts()
    {
        $this->assertTrue(true);
        // Add actual tests
    }
}
```

### **tests/TestCase.php**

```php
<?php

namespace Elects\RoadRunnerQueue\Tests;

use Elects\RoadRunnerQueue\RoadRunnerQueueServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            RoadRunnerQueueServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup test environment
    }
}
```

---

## 📝 **Step 4: Update Namespaces**

Pastikan semua files punya namespace yang benar:

```php
<?php

namespace Elects\RoadRunnerQueue\Jobs;

// Your code
```

**Check list:**
- ✅ `src/Jobs/RoadRunnerJob.php` → `namespace Elects\RoadRunnerQueue\Jobs;`
- ✅ `src/Console/Commands/*` → `namespace Elects\RoadRunnerQueue\Console\Commands;`
- ✅ `src/RoadRunnerQueueServiceProvider.php` → `namespace Elects\RoadRunnerQueue;`

---

## 🔧 **Step 5: Install Dependencies**

```bash
# Install composer dependencies
composer install

# Verify autoload
composer dump-autoload
```

---

## ✅ **Step 6: Verify Package Structure**

Run this checklist:

```bash
# Check composer.json valid
composer validate

# Check autoload works
composer dump-autoload -o

# List all classes
composer show -t
```

**Final structure:**

```
laravel-roadrunner-queue/
├── .github/
│   └── workflows/
│       └── tests.yml
├── config/
│   └── roadrunner-queue.php
├── src/
│   ├── Console/
│   │   └── Commands/
│   │       ├── RoadRunnerRetryCommand.php
│   │       ├── RoadRunnerFailedCommand.php
│   │       ├── RoadRunnerForgetCommand.php
│   │       └── RoadRunnerFlushCommand.php
│   ├── Jobs/
│   │   └── RoadRunnerJob.php
│   └── RoadRunnerQueueServiceProvider.php
├── tests/
│   ├── Feature/
│   ├── Unit/
│   │   └── RoadRunnerJobTest.php
│   └── TestCase.php
├── .gitignore
├── CHANGELOG.md
├── composer.json
├── LICENSE.md
├── phpunit.xml.dist
├── PUBLISHING_GUIDE.md
└── README.md
```

---

## 🚀 **Step 7: Test Locally**

### **Create Test Laravel Project**

```bash
cd ..
composer create-project laravel/laravel test-app
cd test-app
```

### **Add Local Package**

Edit `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../laravel-roadrunner-queue"
        }
    ]
}
```

### **Require Package**

```bash
composer require ebects/laravel-roadrunner-queue @dev
```

### **Test Commands**

```bash
php artisan list rr

# Should show:
#  rr:failed
#  rr:flush
#  rr:forget
#  rr:retry
```

### **Test Job**

```php
<?php
// app/Jobs/TestJob.php

namespace App\Jobs;

use Elects\RoadRunnerQueue\Jobs\RoadRunnerJob;

class TestJob extends RoadRunnerJob
{
    public $tries = 3;
    public $backoff = [10, 30, 60];
    
    protected function process(): void
    {
        info('Test job executed!');
        
        // Test retry
        if ($this->currentAttempt() <= 2) {
            throw new \Exception("Test retry");
        }
    }
    
    public function failed(\Throwable $exception): void
    {
        info('Job failed after all retries');
    }
}
```

```bash
# Dispatch job
php artisan tinker
>>> TestJob::dispatch();

# Check logs
tail -f storage/logs/laravel.log
```

---

## 📤 **Step 8: Commit to Git**

```bash
cd ../laravel-roadrunner-queue

git add .
git commit -m "Initial package structure"

# Create .gitattributes for cleaner exports
cat > .gitattributes << 'EOF'
/tests export-ignore
/.github export-ignore
/.gitattributes export-ignore
/.gitignore export-ignore
/phpunit.xml.dist export-ignore
/PUBLISHING_GUIDE.md export-ignore
EOF

git add .gitattributes
git commit -m "Add .gitattributes"
```

---

## 🌐 **Step 9: Create GitHub Repo**

### **Option 1: Via GitHub CLI**

```bash
gh auth login
gh repo create ebects/laravel-roadrunner-queue --public --source=. --remote=origin
git push -u origin main
```

### **Option 2: Via GitHub Website**

1. Go to https://github.com/new
2. Name: `laravel-roadrunner-queue`
3. Public
4. NO README, NO LICENSE (already have)
5. Create

Then:

```bash
git remote add origin https://github.com/ebects/laravel-roadrunner-queue.git
git branch -M main
git push -u origin main
```

---

## 🏷️ **Step 10: Create Release**

```bash
# Tag version
git tag -a v1.0.0 -m "Initial release v1.0.0"
git push origin v1.0.0

# Create GitHub release
# Go to: https://github.com/ebects/laravel-roadrunner-queue/releases/new
# - Tag: v1.0.0
# - Title: "Initial Release v1.0.0"
# - Description: Copy from CHANGELOG.md
# - Publish release
```

---

## 📦 **Step 11: Submit to Packagist**

1. **Go to:** https://packagist.org
2. **Sign in** with GitHub
3. **Submit Package:**
   - Click "Submit"
   - URL: `https://github.com/ebects/laravel-roadrunner-queue`
   - Click "Check"
   - Click "Submit"

4. **Setup Auto-Update:**
   - Copy webhook URL from Packagist
   - Go to GitHub repo settings
   - Webhooks → Add webhook
   - Paste URL
   - Content type: application/json
   - Events: Just push
   - Add webhook

✅ **DONE!** Package is now public!

---

## 🎉 **Step 12: Test Installation**

```bash
# New project
composer create-project laravel/laravel fresh-test
cd fresh-test

# Install your package
composer require ebects/laravel-roadrunner-queue

# Test
php artisan list rr
```

---

## 📢 **Step 13: Announce**

### **Update README badges**

Now that package is live, badges will work!

### **Social Media**

**Twitter:**
```
🚀 Excited to release Laravel RoadRunner Queue!

Finally - RoadRunner WITH native Laravel retry mechanism!

✅ $tries, $backoff work
✅ failed() gets called
✅ No zombie processes
✅ Artisan commands

composer require ebects/laravel-roadrunner-queue

⭐ https://github.com/ebects/laravel-roadrunner-queue
```

**LinkedIn:**
```
🎉 Just published my first open-source package!

Laravel RoadRunner Queue - solves the retry mechanism problem in RoadRunner.

After months of dealing with failed jobs and zombie processes, I built this solution. Now available for everyone!

Check it out: [link]
```

### **Reddit Posts**

- r/laravel
- r/PHP
- r/webdev

### **Dev.to Article**

Write post:
```
Title: "Solving RoadRunner's Queue Retry Problem in Laravel"

Content:
1. The Problem
2. Existing Solutions
3. My Approach
4. How It Works
5. Installation & Usage
6. Conclusion
```

---

## 📊 **Step 14: Monitor & Maintain**

### **Daily Tasks**

- Check Packagist downloads
- Respond to issues
- Review pull requests

### **Weekly Tasks**

- Update dependencies
- Run tests
- Review analytics

### **Monthly Tasks**

- Plan new features
- Update documentation
- Write blog posts

---

## ✅ **Success Checklist**

- [ ] All files organized
- [ ] Namespaces correct
- [ ] Tests pass locally
- [ ] GitHub repo created
- [ ] Release tag created
- [ ] Packagist published
- [ ] Webhook configured
- [ ] Tested installation
- [ ] README updated
- [ ] Announced on social media

---

## 🎯 **Next Steps**

1. **v1.0.1** - Bug fixes
2. **v1.1.0** - New features (ideas from issues)
3. **v2.0.0** - Major improvements

---

## 🌟 **Tips for Success**

### **Respond Quickly**
- Issues within 24h
- PRs within 48h

### **Good Documentation**
- Clear examples
- Troubleshooting guide
- Video tutorials

### **Engage Community**
- Answer questions
- Accept contributions
- Give credit

### **Quality Matters**
- Keep tests updated
- Maintain code style
- Update dependencies

---

**🎊 CONGRATULATIONS! Your package is LIVE!** 🚀

**Package:** `composer require ebects/laravel-roadrunner-queue`

**Star it on GitHub to help others find it!** ⭐
