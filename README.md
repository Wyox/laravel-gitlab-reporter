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

To use the Gitlab reporter you need to do the following with Laravel 11

Open your `bootstrap/app.php` and add the code shown below that is between the comment to the withExceptions closure
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

For Laravel 9, 10 use you should change the following in your `app/Exceptions/Handler.php` file in your Laravel
project

```php
public function register()
{
    $this->reportable(function (Throwable $e) {
        if (app()->bound('gitlab.report') && $this->shouldReport($e)) {
            app('gitlab.report')->report($e);
        }
    });
}
```

Now setup your .env file to include the following variables:

```
GITLAB_REPORT_URL=https://gitlab.com/
GITLAB_REPORT_TOKEN=
GITLAB_REPORT_PROJECT_ID=
GITLAB_REPORT_LABELS=
GITLAB_USE_CACHE=true
```

I would suggest making a separate user account for the reporter and only let it access Issues and allow it for issue
creation.
This way you can ensure if your server or code gets compromised you won't give full access to the server

To retrieve an access token go to your gitlab server to `profile/personal_access_tokens` and generate a token for using
the API

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

In some cases you don't want reports to contain passwords of your clients. You can extend or replace values in the
configuration file to include more fields that shouldn't show up in a report. All these fields will be replaced
with [redacted]. Fields that are filled with null will also be replaced with [redacted]
