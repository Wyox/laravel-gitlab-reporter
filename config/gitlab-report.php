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

    // Extra field names whose values are replaced with [redacted] in reports. These are added to a built-in list
    // (passwords, tokens, secrets, api keys, authorization, CSRF token, session password hashes, ...) that always
    // applies, see GitlabReportService::REDACTED_FIELDS. Matching ignores case and `_`, `-`, `.` and spaces (so
    // `customer_iban` also covers `customerIban`), and `*` is a wildcard.
    'redacted-fields' => [
        // 'iban',
        // 'customer_*',
    ],

    // Attributes of the authenticated user model that are shown in a report. Everything else is left out.
    'user-fields' => ['id'],

    // Put the bound values into the reported SQL (and the QueryException message). Bindings often contain
    // personal data or secrets, so this is off by default and the SQL is shown with `?` placeholders.
    'sql-bindings' => false,

    // Optional callable that receives an exception message and returns the version to report, for messages that
    // contain user input. Use an invokable class name or [Class::class, 'method'] so `config:cache` keeps working.
    'message-redactor' => null,

    // Parts of a report that can be switched off.
    'context-host' => true,        // server hostname
    'context-client-ip' => true,   // client IP address (personal data under the GDPR)
    'context-user-agent' => true,  // client user agent
    'code-excerpt' => true,        // source lines around the line that threw

    // Maximum number of GitLab lookups (search + create or note) per minute for incidents that aren't cached yet.
    // Protects GitLab and your response times against floods of distinct errors. Null disables the limit.
    'rate-limit' => 30,

    // Seconds before a request to GitLab is given up. Reporting runs inside the failing request. 0 or less means 5.
    'timeout' => env('GITLAB_REPORT_TIMEOUT', 5),

    'cache' => env('GITLAB_USE_CACHE', true),
    // When reporting fails, a warning with the error's class and message is logged. Debug logs the full exception.
    'debug' => env('GITLAB_REPORT_DEBUG', false),
];
