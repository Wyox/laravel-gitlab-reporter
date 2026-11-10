# Laravel Gitlab Report

Create issues for Exceptions that happen on your servers.

This package will create issues in your Gitlab project if Exceptions occur and will post some more debug information to
the issue to help you solve problems.

This package will contact your Gitlab server and checks if an exception has occurred before based on a generated
identifier hash in the issue description. Don't remove this line in the description as it will be the only way for this
package to validate if an exception occurred before.

Gitlab version 9 or higher required.

# Installation

Install with composer

```bash 
composer require wyox/laravel-gitlab-reporter
```

This package supports Laravel 12 (PHP 8.2 or higher) and Laravel 13 (PHP 8.3 or higher), and is tested up to PHP 8.5.

To use the Gitlab reporter, open your `bootstrap/app.php` and add the code shown below between the comments to the withExceptions closure
```php 
    ->withExceptions(function (Exceptions $exceptions) {
        // ADD below
        $exceptions->report(function(\Throwable $e){
            if (app()->bound('gitlab.report')) {
                app('gitlab.report')->report($e);
            }
        });
        // ADD Above
    })
```

Now setup your .env file to include the following variables:

```
GITLAB_REPORT_URL=https://gitlab.com/
GITLAB_REPORT_TOKEN=
GITLAB_REPORT_PROJECT_ID=
GITLAB_REPORT_LABELS=
GITLAB_USE_CACHE=true
```

Reports contain request data, so treat the project they go to as sensitive:

- Use a **private** project. In an internal project every signed-in user of your GitLab instance can read the reports.
- Use a **project access token** (project -> Settings -> Access tokens) with the **Guest** role, only the `api` scope
  and an expiry date. Guest is enough to create issues, add notes and search them. A project access token belongs to a
  bot user without a password, so it isn't blocked when a password expires, and it can't reach your other projects.
- Use `https://` for `GITLAB_REPORT_URL`. Redirects are not followed, so the token is never sent anywhere else.

For your Project ID you need to go to your project -> Settings -> General -> General Project settings. There should be a
box with Project ID

# Adding labels to issues

Adding labels to newly created issues is easy, just add a comma-separated list to `GITLAB_REPORT_LABELS=`

```
GITLAB_REPORT_LABELS=bug,critical
```

If the labels don't exist in Gitlab they will be automatically created.

# Ignoring certain exceptions

Make sure you publish the config as setting exceptions is not possible using an .env file

```bash
php artisan vendor:publish --tag=gitlab-report
```

A file called gitlab-report.php will be created there and you can change settings there. A couple of exceptions have
been added by default

# Hiding fields in reports

Query parameters, POST data, session data, user attributes and `--key=value` console options are redacted before they
are put in a report. A built-in list of field names is always redacted: anything containing `password`, `secret` or
`token`, api keys, `key`, `signature`, `code`, `otp`, `pin`, `authorization`, cookies, two-factor secrets and recovery
codes, and card numbers. That includes Laravel's CSRF token (`_token`), the password hash `AuthenticateSession` keeps in
the session (`password_hash_*`) and the signature of signed URLs. See `GitlabReportService::REDACTED_FIELDS`. Fields you
list in `redacted-fields` are redacted on top of that list, so publishing the config can't switch the defaults off.
Matching ignores case and `_`, `-`, `.` and spaces (`api_token`, `X-Api-Token` and `apiToken` are the same field), `*` is
a wildcard (`customer_*`), and a matching key redacts its whole value, nested arrays included. Values that are URLs, like
the previous and intended URL Laravel keeps in the session, get their query parameters redacted the same way. Redaction
works on copies: the request and session your application keeps using are never changed.

Only the `id` of the logged-in user is reported. Add attributes with `user-fields`; everything else (password hash,
remember token, e-mail, ...) is left out.

The following can't be redacted by field name, so check them for your application:

- **Exception messages** (title, summary and "Caused by") often contain user input. Set `message-redactor` to a callable
  that receives the message and returns what should be reported. Use an invokable class name or
  `[MyRedactor::class, 'method']` rather than a closure, so `php artisan config:cache` keeps working.
- **SQL bindings** are left out by default: the SQL block and `QueryException` messages show `?` placeholders. Set
  `sql-bindings` to `true` to include them.
- **Positional console arguments** (`artisan user:create secret`) are reported as-is, only options are redacted.
- **Secrets in the URL path** (`reset-password/{token}`) show in the Path and URL rows. Only the query string is redacted.
- **Objects** (for example a model stored in the session) are dumped with all their properties; only arrays are redacted.

# Configuration

All options live in the published `config/gitlab-report.php`:

| Option | Default | |
| :----- | :------ | :- |
| `redacted-fields` | `[]` | Extra field names to redact, on top of the built-in list. |
| `user-fields` | `['id']` | Attributes of the logged-in user that are reported. |
| `sql-bindings` | `false` | Put query bindings in the SQL block and `QueryException` messages. |
| `message-redactor` | `null` | Callable that rewrites exception messages before they are reported. |
| `context-host` / `context-client-ip` / `context-user-agent` | `true` | Rows in the Context section. The client IP and user agent are personal data under the GDPR. |
| `code-excerpt` | `true` | The Code section with source lines around the failing line. |
| `rate-limit` | `30` | GitLab lookups per minute for errors that aren't cached yet. `null` disables the limit. |
| `timeout` | `5` (`GITLAB_REPORT_TIMEOUT`) | Seconds before a request to GitLab is given up; 0 or less means 5. Ignored when you bind your own `Gitlab\Client` in the container. |
| `debug` | `false` (`GITLAB_REPORT_DEBUG`) | When reporting fails a warning is logged with the error's class and message; with `debug` the full exception is logged. |

# What's in a report

Each issue description is built from a number of sections:

- **Exception summary** - type, message, file/line and (for HTTP) the request method, URL and authentication state.
- **Context** - environment, Laravel/PHP version, host, timestamp and (for HTTP) client IP and user agent.
- **Code** - a source excerpt around the line that threw the exception.
- **SQL** - the failing query with `?` placeholders (only for `QueryException`).
- **Query params / POST / Session / User** - redacted request data and the allow-listed user attributes (HTTP only).
- **Trace** - the stack trace without call arguments, followed by a **Caused by** section for every previous exception
  in the chain.

Values that come from the client (message, path, URL, user agent, console arguments) are rendered as inline code, and
code blocks use a fence longer than any backticks in their content, so reported data can't inject links, images,
@mentions or quick actions into the issue. The title puts the message in a code span too, so mentions and `#123`
references in a message don't notify anyone or link issues. Invalid UTF-8 is replaced and NUL bytes and bidirectional
control characters are removed, so a malformed value can't make GitLab reject the report. Table cells and dumped strings
are cut at 10000 characters.

When a report can't be sent (GitLab unreachable, token rejected, ...) a warning is written to your application log. When
the `rate-limit` is reached, a warning is logged once per minute.

# Duplicate issues and recurrences

Exceptions are deduplicated using a hash of the route (the template, e.g. `users/{user}`, not the concrete path or the
Host header) or command, exception class and a normalized message (dynamic ids, UUIDs and hashes are masked) plus the
file and line. When an open issue already exists for an
exception, a throttled "occurred again" note is added to it instead of creating a duplicate. Only open issues created by
the reporter's own token that contain the exact `Identifier:` line count, so an issue someone else writes can't take
over the reports. The throttling
window is shared with the local cache (`GITLAB_USE_CACHE`).

# Development

Everything runs in Docker, no local PHP needed.

```bash
docker compose up                                  # workbench app on http://localhost:8000
docker compose run --rm app vendor/bin/phpunit     # quick test run (add --filter ...)
docker compose run --rm test 12                    # one Laravel version (12, 13 or 14 = dev-master)
PHP_VERSION=8.2 docker compose run --rm test 12    # ...on another PHP version
bin/matrix                                         # every supported PHP/Laravel combination
bin/matrix laravel13 php8.4                        # a subset
```

The workbench (`workbench/`, served by Orchestra Testbench) has routes that throw request, SQL and session exceptions,
plus a console command: `docker compose exec app vendor/bin/testbench boom`. Edits to `src/` are live, just refresh.

By default the reports go to a fake GitLab API (`docker/gitlab-mock/server.php`) that rejects what GitLab would reject
(title > 255 characters, description > 1048576 characters, invalid UTF-8) and writes each issue to
`docker/gitlab-mock/issues/<id>.md`. Delete those files to start over. To test against a real GitLab, set
`GITLAB_REPORT_URL`, `GITLAB_REPORT_TOKEN` and `GITLAB_REPORT_PROJECT_ID` before `docker compose up`.
Use `APP_PORT` / `GITLAB_MOCK_PORT` if 8000 / 8081 are taken.

The tests in `tests/Integration` boot a real Laravel app and only fake the HTTP calls to GitLab, asserting on the exact
requests that would be sent.
