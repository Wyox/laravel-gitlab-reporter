<?php

namespace Wyox\GitlabReport;


class ServiceProvider extends \Illuminate\Support\ServiceProvider {


    public function boot() {
        $this->publishes([
            __DIR__.'/../config/gitlab-report.php' => config_path('gitlab-report.php'),
        ], 'gitlab-report');
    }
    public function register() {
        $this->mergeConfigFrom( __DIR__.'/../config/gitlab-report.php', 'gitlab-report');

        $this->app->singleton(GitlabReportService::class, function($app) {

            $config     = $app->make('config');
            $url        = $config->get('gitlab-report.url');
            $token      = $config->get('gitlab-report.token');
            $project_id = $config->get('gitlab-report.project_id');

            return new GitlabReportService($url,$token,$project_id);
        });

        $this->app->alias(GitlabReportService::class, 'gitlab.report');
    }
    public function provides() {
        return ['gitlab.report', GitlabReportService::class];
    }
}