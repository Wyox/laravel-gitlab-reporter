<?php

use Symfony\Component\Console\Exception\CommandNotFoundException;

return [
    'url' => env('GITLAB_REPORT_URL'),
    'token' => env('GITLAB_REPORT_TOKEN'),
    'project_id' => env('GITLAB_REPORT_PROJECT_ID'),
    'labels' => env('GITLAB_REPORT_LABELS', ''),
    'ignore-exceptions' => [
        CommandNotFoundException::class,
    ],
    'redacted-fields' => [
        'password',
        'password_confirmation',
    ],
    'cache' => env('GITLAB_USE_CACHE', true),
    'debug' => env('GITLAB_REPORT_DEBUG', false),
];
