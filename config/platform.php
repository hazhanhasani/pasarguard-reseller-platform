<?php
return [
    'tick' => [
        'provider_operation_batch' => (int) env('PLATFORM_PROVIDER_OPERATION_BATCH', 50),
        'usage_batch' => (int) env('PLATFORM_USAGE_BATCH', 100),
        'output_batch' => (int) env('PLATFORM_OUTPUT_BATCH', 100),
        'reconcile_batch' => (int) env('PLATFORM_RECONCILE_BATCH', 100),
        'queue_jobs' => (int) env('PLATFORM_QUEUE_MAX_JOBS', 200),
        'queue_max_time' => (int) env('PLATFORM_QUEUE_MAX_TIME', 50),
    ],
    'provider_retry' => [
        'max_attempts' => (int) env('PLATFORM_PROVIDER_RETRY_MAX', 8),
        'base_seconds' => (int) env('PLATFORM_PROVIDER_RETRY_BASE', 30),
        'max_seconds' => (int) env('PLATFORM_PROVIDER_RETRY_MAX_SECONDS', 1800),
    ],
];
