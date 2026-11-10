<?php

namespace Wyox\GitlabReport\Tests\Unit;

use Gitlab\Client;
use Http\Client\Common\Plugin\RedirectPlugin;
use Wyox\GitlabReport\GitlabReportService;
use Wyox\GitlabReport\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_service_is_bound_in_container(): void
    {
        $this->assertTrue($this->app->bound(GitlabReportService::class));
        $this->assertTrue($this->app->bound('gitlab.report'));
    }

    public function test_service_is_singleton(): void
    {
        $instance1 = $this->app->make(GitlabReportService::class);
        $instance2 = $this->app->make(GitlabReportService::class);

        $this->assertSame($instance1, $instance2);
    }

    public function test_alias_resolves_to_service(): void
    {
        $service = $this->app->make('gitlab.report');

        $this->assertInstanceOf(GitlabReportService::class, $service);
    }

    public function test_built_client_does_not_follow_redirects(): void
    {
        $client = (new \ReflectionProperty(GitlabReportService::class, 'client'))->getValue($this->app->make(GitlabReportService::class));
        $builder = (new \ReflectionMethod(Client::class, 'getHttpClientBuilder'))->invoke($client);
        $plugins = (new \ReflectionProperty($builder, 'plugins'))->getValue($builder);

        $this->assertNotEmpty($plugins);
        $this->assertEmpty(array_filter($plugins, fn ($plugin) => $plugin instanceof RedirectPlugin));
    }

    public function test_config_is_merged(): void
    {
        $config = $this->app['config']->get('gitlab-report');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('url', $config);
        $this->assertArrayHasKey('token', $config);
        $this->assertArrayHasKey('project_id', $config);
        $this->assertArrayHasKey('labels', $config);
        $this->assertArrayHasKey('ignore-exceptions', $config);
        $this->assertArrayHasKey('redacted-fields', $config);
    }
}
