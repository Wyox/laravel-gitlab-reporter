# Migration guide from version 1 to version 2

Change your `Handler.php` from 

```php
public function report(Exception $exception)
{
    // Ignore Gitlab Report in code coverage
    // @codeCoverageIgnoreStart
    if(app()->bound('gitlab.report') && $this->shouldReport($exception)){
        app('gitlab.report')->report($exception);
    }
    // @codeCoverageIgnoreEnd

    parent::report($exception);
}
```

to

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


