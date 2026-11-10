<?php

namespace Workbench\App\Providers;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;
use Throwable;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Same hook the README tells users to add in bootstrap/app.php (withExceptions -> report).
        $this->app->make(ExceptionHandler::class)->reportable(function (Throwable $e) {
            if (app()->bound('gitlab.report')) {
                app('gitlab.report')->report($e);
            }
        });
    }
}
