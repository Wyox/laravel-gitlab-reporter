# Laravel Gitlab Report

Create issues for Exceptions that happen on your servers.

This package will create issues in your Gitlab project if Exceptions occur and will post some more debug information to the issue to help you solve problems.

This package will contact your Gitlab server and checks if an exception has occurred before based on a generated identifier hash in the issue description. Don't remove this line in the description as it will be the only way for this package to validate if an exception occurred before. 


# Installation

Install with composer

```bash 
composer require wyox/laravel-gitlab-reporter
```

To use the Gitlab reporter you should change the following in your `app/Exceptions/Handler.php` file in your Laravel project

```php
public function report(Exception $exception)
{
    // Ignore Gitlab Report in code coverage
    // @codeCoverageIgnoreStart
    if(env('APP_ENV') == 'production' && $this->shouldReport($exception)){
        app('gitlab.report')->report($exception);
    }
    // @codeCoverageIgnoreEnd

    parent::report($exception);
}
```

To test if your connection and settings work you could temporarily remove the `env('APP_ENV')` check, run the settings locally and see if everything works



Now setup your .env file to include the following variables:

```
GITLAB_REPORT_URL=https://gitlab.com/
GITLAB_REPORT_TOKEN=
GITLAB_REPORT_PROJECT_ID=
GITLAB_REPORT_LABELS=
```


I would suggest making a seperate user account for the reporter and only let it access Issues and allow it for issue creation.
This way you can ensure if your server or code gets compromised you won't give full access to the server
 
To retrieve an access token go to your gitlab server to `profile/personal_access_tokens` and generate a token for using the API


For your Project ID you need to go to your project -> Settings -> General -> General Project settings. There should be a box with Project ID


# Adding labels to issues

Adding labels to newly created issues is easy, just add a comma-separated list to `GITLAB_REPORT_LABELS=`
```
GITLAB_REPORT_LABELS=bug,critical
```

If the labels don't exist in Gitlab they will be automatically created.