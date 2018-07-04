# Installation


If you are only using this package in production go to your `app/Exceptions/Handler.php` file


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



Now setup your .env file to include the following variables:

```
GITLAB_REPORT_URL=https://gitlab.com/
GITLAB_REPORT_TOKEN=
GITLAB_REPORT_PROJECT_ID=
```


I would suggest making a seperate user account for the reporter and only let it access Issues and allow it for issue creation.
This way you can ensure if your server or code gets compromised you won't give full access to the server
 
To retrieve an access token go to your gitlab server to `profile/personal_access_tokens` and generate a token for using the API






For your Project ID you need to go to your project -> Settings -> General -> General Project settings. There should be a box with Project ID
