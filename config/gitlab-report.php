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
    // Field names whose values are replaced with [redacted] in reports.
    // Matching is case-insensitive (so "Authorization" and "AUTHORIZATION" both match).
    'redacted-fields' => [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'access_token',
        'refresh_token',
        'api_key',
        'apikey',
        'secret',
        'client_secret',
        'authorization',
        'auth',
        'credit_card',
        'card_number',
        'cvv',
        'ssn',
        'private_key',
    ],
    'cache' => env('GITLAB_USE_CACHE', true),
    'debug' => env('GITLAB_REPORT_DEBUG', false),
];
