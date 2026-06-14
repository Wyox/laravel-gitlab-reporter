<?php

namespace Wyox\GitlabReport\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Wyox\GitlabReport\ServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'GitlabReport' => \Wyox\GitlabReport\Facade::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('gitlab-report.url', 'https://gitlab.example.com');
        $app['config']->set('gitlab-report.token', 'test-token');
        $app['config']->set('gitlab-report.project_id', '123');
        $app['config']->set('gitlab-report.labels', 'bug,automated');
        $app['config']->set('gitlab-report.cache', false);
        $app['config']->set('gitlab-report.debug', true);
        $app['config']->set('gitlab-report.ignore-exceptions', []);
        $app['config']->set('gitlab-report.redacted-fields', ['password', 'password_confirmation', 'secret']);
    }
}
