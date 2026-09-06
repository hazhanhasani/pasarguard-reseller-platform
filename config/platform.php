<?php
return [
    'version' => env('PLATFORM_VERSION', '0.10.0-dev'),
    'tick' => [
        'provider_operation_batch' => (int) env('PLATFORM_PROVIDER_OPERATION_BATCH', 50),
        'usage_batch' => (int) env('PLATFORM_USAGE_BATCH', 100),
        'output_batch' => (int) env('PLATFORM_OUTPUT_BATCH', 100),
        'reconcile_batch' => (int) env('PLATFORM_RECONCILE_BATCH', 100),
        'payment_batch' => (int) env('PLATFORM_PAYMENT_BATCH', 50),
        'queue_jobs' => (int) env('PLATFORM_QUEUE_MAX_JOBS', 200),
        'queue_max_time' => (int) env('PLATFORM_QUEUE_MAX_TIME', 50),
    ],
    'provider_retry' => [
        'max_attempts' => (int) env('PLATFORM_PROVIDER_RETRY_MAX', 8),
        'base_seconds' => (int) env('PLATFORM_PROVIDER_RETRY_BASE', 30),
        'max_seconds' => (int) env('PLATFORM_PROVIDER_RETRY_MAX_SECONDS', 1800),
    ],
    'health' => [
        'provider_batch' => (int) env('PLATFORM_HEALTH_PROVIDER_BATCH', 100),
        'provider_stale_seconds' => (int) env('PLATFORM_PROVIDER_STALE_SECONDS', 900),
        'queue_backlog_warning' => (int) env('PLATFORM_QUEUE_BACKLOG_WARNING', 500),
    ],
    'backup' => [
        'keep_last' => (int) env('PLATFORM_BACKUP_KEEP_LAST', 10),
        'max_bytes' => (int) env('PLATFORM_BACKUP_MAX_BYTES', 10_737_418_240),
        'persistent_paths' => ['public/uploads'],
        'exclude_tables' => ['cache','cache_locks','sessions','jobs','failed_jobs','backups','update_history'],
    ],
    'update' => [
        'max_package_bytes' => (int) env('PLATFORM_UPDATE_MAX_BYTES', 134_217_728),
        'max_entries' => (int) env('PLATFORM_UPDATE_MAX_ENTRIES', 10_000),
        'max_uncompressed_bytes' => (int) env('PLATFORM_UPDATE_MAX_UNCOMPRESSED_BYTES', 536_870_912),
        'max_entry_bytes' => (int) env('PLATFORM_UPDATE_MAX_ENTRY_BYTES', 67_108_864),
    ],
];
