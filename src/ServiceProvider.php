<?php

namespace Wyox\GitlabReport;

/**
 * Class ServiceProvider
 *
 * @package Wyox\GitlabReport
 */
class ServiceProvider extends \Illuminate\Support\ServiceProvider
{

    /**
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/gitlab-report.php' => config_path('gitlab-report.php'),
        ], 'gitlab-report');
    }

    /**
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/gitlab-report.php', 'gitlab-report');

        $this->app->singleton(
            GitlabReportService::class,
            function ($app) {
                $config = array_merge(
                    [
                        'url' => null,
                        'token' => null,
                        'project_id' => null,
                        'labels' => '',
                        'ignore-exceptions' => [],
                        'redacted-fields' => [],
                        'debug' => false
                    ],
                    $app->make('config')->get('gitlab-report', [])
                );

                return new GitlabReportService($config);
            }
        );

        $this->app->alias(GitlabReportService::class, 'gitlab.report');
    }

    /**
     * @return array
     */
    public function provides()
    {
        return ['gitlab.report', GitlabReportService::class];
    }
}
