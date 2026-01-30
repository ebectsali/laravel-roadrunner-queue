# 🐛 BUG FIX: Job ID Changes on Retry

## 🔍 **Bug Report**

### **Symptom:**
Job ID berubah saat retry, dan attempt counter reset ke 1!

```
[07:00:27] job_id: NewInsertTujuanJob:7195, attempt:2  ✅ Correct
[07:00:57] job_id: NewInsertTujuanJob:3, attempt:1     ❌ WRONG!
           ^                          ^
           Different job!             Reset counter!
```

**Expected:** Job dengan ID 7195 retry ke attempt 3  
**Actual:** Job baru dengan ID 3, attempt kembali ke 1  

---

## 🎯 **Root Cause**

Di `RoadRunnerJob.php`, method `retryJob()` menggunakan **array_values()** untuk reconstruct job:

```php
// ❌ BROKEN CODE (v1.0.0)
protected function retryJob(int $delay): void
{
    $jobClass = get_class($this);
    $properties = get_object_vars($this);
    
    // Remove framework properties
    unset(
        $properties['job'],
        $properties['connection'],
        // ... etc
    );
    
    // ❌ PROBLEM: Pass by position!
    $newJob = new $jobClass(...array_values($properties));
    
    dispatch($newJob)->delay(...);
}
```

### **What Goes Wrong:**

**Step 1: Get properties**
```php
$properties = [
    'idSuratMasuk' => 7195,        // ✅ Constructor param 1
    'idPenyampaianSurat' => null,  // ✅ Constructor param 2
    'isReset' => false,            // ✅ Constructor param 3
    'tries' => 3,                  // ❌ NOT constructor param!
    'backoff' => [10, 30, 60],     // ❌ NOT constructor param!
    'timeout' => 120,              // ❌ NOT constructor param!
    'queue' => 'jurnal',           // ❌ NOT constructor param!
    'deleteWhenMissingModels' => true,
    // ... more properties
];
```

**Step 2: array_values() converts to indexed array**
```php
array_values($properties) = [
    0 => 7195,              // ✅ param 1: $idSuratMasuk
    1 => null,              // ✅ param 2: $idPenyampaianSurat
    2 => false,             // ✅ param 3: $isReset
    3 => 3,                 // ❌ param 4 ??? (tries)
    4 => [10, 30, 60],      // ❌ param 5 ??? (backoff)
    5 => 120,               // ❌ param 6 ??? (timeout)
    // ...
];
```

**Step 3: Constructor call**
```php
// Constructor signature:
public function __construct($idSuratMasuk, $idPenyampaianSurat = null, $isReset = false)

// Called with:
new NewInsertTujuanJob(7195, null, false, 3, [10,30,60], 120, ...)
//                      ^     ^     ^      ^
//                      OK    OK    OK     EXTRA PARAMS!
```

**PHP will:**
1. Accept first 3 params correctly
2. **Ignore or mishandle** extra params (depending on PHP version/strictness)
3. In some cases, PHP might use default values or throw warnings

**Result:** Job gets constructed **incorrectly**!

---

## 🔬 **Why ID Changed?**

Possible scenarios:

### **Scenario 1: Property Order Changed**
```php
// If property order is different across instances:
First job:  ['idSuratMasuk' => 7195, 'tries' => 3, ...]
Retry job:  ['tries' => 3, 'idSuratMasuk' => 7195, ...]  // Different order!

array_values() gives:
First:  [7195, 3, ...]
Retry:  [3, 7195, ...]  // ❌ ID=3 passed as first param!
```

### **Scenario 2: Constructor Defaults**
```php
public function __construct($idSuratMasuk, $idPenyampaianSurat = null, $isReset = false)

// If extra params ignored:
new NewInsertTujuanJob(tries_value, ...)
// $idSuratMasuk = 3 (the tries value!)
```

---

## ✅ **Solution**

**Use serialize/unserialize untuk exact copy!**

```php
// ✅ FIXED CODE (v1.0.1)
protected function retryJob(int $delay): void
{
    // Serialize and unserialize to create exact copy
    $serialized = serialize($this);
    $newJob = unserialize($serialized);
    
    // Dispatch with delay
    dispatch($newJob)
        ->onQueue($this->queue ?? 'default')
        ->delay(now()->addSeconds($delay));
}
```

### **Why This Works:**

1. **serialize()** captures **EXACT state** of job object
   - All properties with correct values
   - All nested objects
   - Proper object structure

2. **unserialize()** recreates **IDENTICAL object**
   - No constructor call needed
   - All properties intact
   - Same state as original

3. **No reconstruction errors**
   - No parameter position issues
   - No missing properties
   - No type mismatches

---

## 📊 **Comparison**

### **Before (Broken):**
```php
// Original job
idSuratMasuk: 7195

// array_values() reconstruction
[7195, null, false, 3, [...], 120, ...]

// Constructor gets wrong params
new Job(7195, null, false, 3, ...) // ❌ Extra params!

// Result: Unpredictable behavior
```

### **After (Fixed):**
```php
// Original job
idSuratMasuk: 7195

// serialize() → unserialize()
Exact copy with all properties intact

// Result: Perfect clone
idSuratMasuk: 7195 ✅
```

---

## 🧪 **Testing**

### **Test Case:**

```php
class TestJob extends RoadRunnerJob
{
    public $tries = 3;
    public $backoff = [10, 30, 60];
    
    public $jobId;
    public $data;
    
    public function __construct($jobId, $data)
    {
        $this->jobId = $jobId;
        $this->data = $data;
    }
    
    protected function process(): void
    {
        Log::info('Processing', [
            'job_id' => $this->jobId,
            'data' => $this->data
        ]);
        
        // Always fail to test retry
        throw new \Exception('Test retry');
    }
}

// Dispatch
TestJob::dispatch(12345, ['test' => 'data']);
```

### **Expected Logs (Before Fix):**
```
❌ BROKEN:
[INFO] job_id: 12345, attempt: 1
[ERROR] Failed, retry in 10s
[INFO] job_id: 3, attempt: 1  ← WRONG! ID changed!
```

### **Expected Logs (After Fix):**
```
✅ FIXED:
[INFO] job_id: 12345, attempt: 1
[ERROR] Failed, retry in 10s
[INFO] job_id: 12345, attempt: 2  ← CORRECT! Same ID!
[ERROR] Failed, retry in 30s
[INFO] job_id: 12345, attempt: 3  ← CORRECT! Same ID!
```

---

## 📝 **Update Instructions**

### **For Package Users:**

```bash
# Update package
composer update ebects/laravel-roadrunner-queue

# Or specify version
composer require ebects/laravel-roadrunner-queue:^1.0.1
```

### **For Local Development:**

Replace `retryJob()` method in:
```
vendor/ebects/laravel-roadrunner-queue/src/Jobs/RoadRunnerJob.php
```

Or wait for v1.0.1 release!

---

## 🎯 **Version Info**

**Bug introduced:** v1.0.0  
**Fixed in:** v1.0.1  
**Impact:** HIGH (job retry mechanism broken)  
**Priority:** CRITICAL  

---

## ✅ **Verification**

After fix, verify with:

```bash
# Watch logs during retry
tail -f storage/logs/laravel.log | grep job_id

# Should see SAME job_id across all attempts:
job_id: 12345, attempt: 1
job_id: 12345, attempt: 2  ✅ Same ID!
job_id: 12345, attempt: 3  ✅ Same ID!
```

---

## 🎊 **Summary**

**Problem:** `array_values()` caused parameter position mismatch  
**Solution:** Use `serialize/unserialize` for exact copy  
**Result:** Job retry now works correctly! ✅  

**Credits:** Bug reported by @aleekhabib - Thank you! 🙏

---

**Version:** Fixed in RoadRunnerJob v1.0.1  
**File:** `src/Jobs/RoadRunnerJob.php`  
**Method:** `retryJob()`
