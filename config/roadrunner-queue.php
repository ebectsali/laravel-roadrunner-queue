<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Attempt Counter Cache Driver
    |--------------------------------------------------------------------------
    |
    | The cache driver used to track job retry attempts. Default is 'redis'
    | but you can use any Laravel cache driver.
    |
    */

    'cache_driver' => env('RR_QUEUE_CACHE_DRIVER', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Attempt Counter TTL
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) to keep attempt counters in cache after job
    | completion or failure. Default is 86400 (24 hours).
    |
    */

    'attempt_ttl' => env('RR_QUEUE_ATTEMPT_TTL', 86400),

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix for cache keys used to track job attempts. Useful if you want
    | to namespace keys or avoid conflicts.
    |
    */

    'cache_prefix' => env('RR_QUEUE_CACHE_PREFIX', 'rr_job_attempt:'),

    /*
    |--------------------------------------------------------------------------
    | Default Queue
    |--------------------------------------------------------------------------
    |
    | The default queue to dispatch retry jobs to if not specified.
    |
    */

    'default_queue' => env('RR_QUEUE_DEFAULT', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Enable or disable detailed logging for job retry mechanism.
    |
    */

    'logging' => [
        'enabled' => env('RR_QUEUE_LOGGING', true),
        'channel' => env('RR_QUEUE_LOG_CHANNEL', null), // null = default channel
    ],

];
