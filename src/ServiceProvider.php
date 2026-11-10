<?php

namespace Wyox\GitlabReport;

use Gitlab\Client;
use Gitlab\HttpClient\Builder;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Psr7\HttpFactory;

/**
 * Class ServiceProvider.
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
                    $this->defaultConfig(),
                    $app->make('config')->get('gitlab-report', [])
                );

                return new GitlabReportService($config, $this->client($config));
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


    /**
     * An application-bound Gitlab\Client is used as-is. Otherwise build one with a timeout (the default HTTP client
     * has none, so a hanging GitLab would hang every failing request with it) and without following redirects.
     * A timeout of 0 or less would mean "wait forever", so it falls back to the default of 5 seconds.
     */
    private function client(array $config): Client
    {
        if ($this->app->bound(Client::class)) {
            return $this->app->make(Client::class);
        }

        $timeout = (float) $config['timeout'];
        $timeout = $timeout > 0 ? $timeout : 5.0;
        $factory = new HttpFactory();

        return GitlabReportService::client(new Builder(
            new Guzzle(['timeout' => $timeout, 'connect_timeout' => $timeout]),
            $factory,
            $factory,
            $factory,
        ));
    }

    private function defaultConfig(): array
    {
        return [
            'url' => null,
            'token' => null,
            'project_id' => null,
            'labels' => '',
            'ignore-exceptions' => [],
            'redacted-fields' => [],
            'user-fields' => ['id'],
            'sql-bindings' => false,
            'message-redactor' => null,
            'context-host' => true,
            'context-client-ip' => true,
            'context-user-agent' => true,
            'code-excerpt' => true,
            'rate-limit' => 30,
            'timeout' => 5,
            'cache' => true,
            'debug' => false,
        ];
    }
}
