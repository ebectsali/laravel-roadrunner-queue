# 📦 Publishing Guide: Laravel RoadRunner Queue

Complete guide untuk publish package ke Packagist dan membuat open source project!

---

## 🎯 **Pre-Publishing Checklist**

### **1. Package Structure**

Pastikan struktur folder seperti ini:

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
│   └── Unit/
├── .gitignore
├── CHANGELOG.md
├── composer.json
├── LICENSE.md
├── phpunit.xml
└── README.md
```

### **2. Update Files**

#### **composer.json**
```json
{
    "name": "ebects/laravel-roadrunner-queue",
    "description": "Laravel native queue retry mechanism for RoadRunner",
    "authors": [
        {
            "name": "Your Name",
            "email": "your.email@example.com"
        }
    ]
}
```

#### **.gitignore**
```
/vendor
composer.lock
.phpunit.result.cache
.DS_Store
Thumbs.db
phpunit.xml
.idea/
.vscode/
*.log
coverage/
```

---

## 🚀 **Step-by-Step Publishing**

### **Step 1: Create GitHub Repository**

```bash
# Initialize git
git init

# Add all files
git add .

# First commit
git commit -m "Initial release v1.0.0"

# Create GitHub repo (via GitHub website or CLI)
gh repo create ebects/laravel-roadrunner-queue --public --source=. --remote=origin

# Push to GitHub
git branch -M main
git push -u origin main
```

### **Step 2: Create Release Tag**

```bash
# Create annotated tag
git tag -a v1.0.0 -m "Release version 1.0.0"

# Push tag
git push origin v1.0.0
```

### **Step 3: Register on Packagist**

1. **Go to:** https://packagist.org
2. **Sign in** with GitHub account
3. **Click "Submit"**
4. **Enter repo URL:** `https://github.com/ebects/laravel-roadrunner-queue`
5. **Click "Check"** → Packagist validates composer.json
6. **Click "Submit"**

✅ **Done!** Package is now available!

### **Step 4: Setup Auto-Update Hook**

Di GitHub repository settings:
1. Go to **Settings → Webhooks**
2. Click **Add webhook**
3. **Payload URL:** (dari Packagist profile page)
4. **Content type:** application/json
5. **Events:** Just the push event
6. Click **Add webhook**

Now setiap push/tag otomatis update di Packagist! 🎉

---

## 📝 **After Publishing**

### **1. Add Badges to README**

Update README dengan badges yang working:

```markdown
[![Latest Version on Packagist](https://img.shields.io/packagist/v/ebects/laravel-roadrunner-queue.svg?style=flat-square)](https://packagist.org/packages/ebects/laravel-roadrunner-queue)
[![Total Downloads](https://img.shields.io/packagist/dt/ebects/laravel-roadrunner-queue.svg?style=flat-square)](https://packagist.org/packages/ebects/laravel-roadrunner-queue)
[![Tests](https://github.com/ebects/laravel-roadrunner-queue/workflows/Tests/badge.svg)](https://github.com/ebects/laravel-roadrunner-queue/actions)
```

### **2. Update Package Description**

Di Packagist dashboard, update:
- Keywords
- Description
- Homepage URL
- Support links

### **3. Create GitHub Release**

```bash
# Via GitHub website:
# 1. Go to Releases
# 2. Click "Create a new release"
# 3. Choose tag: v1.0.0
# 4. Title: "Initial Release v1.0.0"
# 5. Copy changelog content
# 6. Publish release
```

---

## 🎯 **Testing Package Installation**

### **Test in Fresh Laravel Project**

```bash
# Create test project
composer create-project laravel/laravel test-project
cd test-project

# Install your package
composer require ebects/laravel-roadrunner-queue

# Verify installation
php artisan list rr

# Should show:
#  rr:failed
#  rr:flush
#  rr:forget
#  rr:retry
```

### **Test Job Creation**

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
        // Test logic
        info('Job processed!');
    }
}

// Dispatch
TestJob::dispatch();
```

---

## 📢 **Promotion**

### **1. Social Media**

**Twitter/X:**
```
🚀 Just released Laravel RoadRunner Queue!

Get Laravel's native retry mechanism in RoadRunner:
✅ $tries, $backoff, failed() work!
✅ No zombie processes
✅ Auto retry with backoff
✅ Artisan commands

composer require ebects/laravel-roadrunner-queue

#Laravel #PHP #RoadRunner
```

**Reddit (r/laravel, r/PHP):**
```
[Package] Laravel RoadRunner Queue - Native retry support for RoadRunner

After struggling with RoadRunner's lack of retry support, I built this package.

Features:
- Automatic retry with exponential backoff
- failed() method actually works
- Artisan commands (rr:retry, rr:failed, etc)
- Zero configuration

Check it out: https://github.com/ebects/laravel-roadrunner-queue
```

**Laravel News:**
Submit via: https://laravel-news.com/submit-package

### **2. Community Forums**

- Laravel.io
- Laracasts Forum
- Dev.to
- Medium article

### **3. Show Your Work**

Write blog post:
```
Title: "How I Solved RoadRunner's Queue Retry Problem in Laravel"

Content:
- The problem you faced
- Solutions you tried
- How the package works
- Real-world examples
- Installation guide
```

---

## 🔄 **Releasing Updates**

### **Minor Update (Bug Fix)**

```bash
# 1. Make changes
git add .
git commit -m "Fix: Something"

# 2. Update version
# Edit composer.json, CHANGELOG.md

# 3. Tag and push
git tag -a v1.0.1 -m "Bug fixes"
git push origin v1.0.1
git push origin main
```

### **Feature Update**

```bash
# v1.1.0
git tag -a v1.1.0 -m "New features"
git push origin v1.1.0
git push origin main
```

### **Breaking Change**

```bash
# v2.0.0
git tag -a v2.0.0 -m "Breaking changes"
git push origin v2.0.0
git push origin main
```

---

## 📊 **Monitoring Success**

### **Track Metrics**

- **Packagist Downloads:** Check daily/weekly/monthly
- **GitHub Stars:** Track growth
- **Issues:** Respond quickly
- **Pull Requests:** Review and merge

### **Engage Community**

- Respond to issues within 24h
- Welcome contributions
- Update documentation
- Fix bugs promptly

---

## 🎓 **Maintenance Tips**

### **1. Keep Dependencies Updated**

```bash
# Check outdated packages
composer outdated

# Update dependencies
composer update
```

### **2. Run Tests**

```bash
composer test
```

### **3. Code Style**

```bash
composer format
```

### **4. Documentation**

- Keep README updated
- Add examples for common use cases
- Document breaking changes
- Update CHANGELOG

---

## 🌟 **Making It Popular**

### **Quality Indicators**

✅ **Good Documentation**
- Clear README
- Usage examples
- Troubleshooting guide

✅ **Active Maintenance**
- Quick issue responses
- Regular updates
- Clear roadmap

✅ **Community Engagement**
- Blog posts
- Tutorial videos
- Conference talks

✅ **Social Proof**
- GitHub stars
- Downloads count
- Testimonials

---

## 💰 **Optional: Sponsorship**

Setup GitHub Sponsors:
1. Create `.github/FUNDING.yml`
2. Add sponsor links
3. Offer perks for sponsors

```yaml
# .github/FUNDING.yml
github: [yourusername]
custom: ["https://buymeacoffee.com/yourusername"]
```

---

## ✅ **Final Checklist**

Before announcing:

- [ ] All tests passing
- [ ] Documentation complete
- [ ] README has examples
- [ ] CHANGELOG updated
- [ ] License file present
- [ ] GitHub repo public
- [ ] Packagist published
- [ ] Badges working
- [ ] Tested in fresh Laravel project
- [ ] GitHub release created

---

## 🎉 **Success!**

Your package is now live! 🚀

**Next steps:**
1. Monitor issues
2. Engage with users
3. Plan v1.1.0 features
4. Write blog post
5. Submit to Laravel News

---

**Package URL:** `composer require ebects/laravel-roadrunner-queue`

**🌟 Help others find it - share on social media!**
