<?php

namespace Wyox\GitlabReport\Tests\Unit;

use Exception;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Wyox\GitlabReport\GitlabReportService;
use Wyox\GitlabReport\Tests\TestCase;

class GitlabReportServiceTest extends TestCase
{
    public function test_service_can_be_instantiated_with_config(): void
    {
        $config = [
            'url' => 'https://gitlab.example.com',
            'token' => 'test-token',
            'project_id' => '123',
            'labels' => 'bug,critical',
            'ignore-exceptions' => [],
            'redacted-fields' => ['password'],
            'cache' => false,
            'debug' => false,
        ];

        $service = new GitlabReportService($config);

        $this->assertInstanceOf(GitlabReportService::class, $service);
    }

    public function test_service_can_be_instantiated_without_credentials(): void
    {
        $service = new GitlabReportService([]);

        $this->assertInstanceOf(GitlabReportService::class, $service);
    }

    public function test_service_resolved_from_container(): void
    {
        $service = $this->app->make(GitlabReportService::class);

        $this->assertInstanceOf(GitlabReportService::class, $service);
    }
}
