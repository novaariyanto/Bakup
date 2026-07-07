<?php

return [

    'binary_path' => env('MYDUMPER_BINARY', 'mydumper'),

    'min_version' => env('MYDUMPER_MIN_VERSION', '0.10.0'),

    'staging_root' => env('MYDUMPER_STAGING_ROOT', storage_path('app/mydumper-exports')),

    'default_threads' => (int) env('MYDUMPER_DEFAULT_THREADS', 4),

    'job_timeout' => (int) env('MYDUMPER_JOB_TIMEOUT', 86400),

    'progress_poll_ttl' => (int) env('MYDUMPER_PROGRESS_POLL_TTL', 21600),

    'log_channel' => 'mydumper',

];
