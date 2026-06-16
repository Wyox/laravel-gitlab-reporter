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

    public function test_redaction_is_case_insensitive(): void
    {
        $service = new GitlabReportService([
            'redacted-fields' => ['password', 'authorization'],
        ]);

        $redact = (new \ReflectionClass($service))->getMethod('redact');
        $redact->setAccessible(true);

        $this->assertEquals('[redacted]', $redact->invoke($service, 'Password', 'hunter2'));
        $this->assertEquals('[redacted]', $redact->invoke($service, 'AUTHORIZATION', 'Bearer abc'));
        $this->assertEquals('bob', $redact->invoke($service, 'username', 'bob'));
    }

    public function test_redaction_preserves_non_redacted_value_types(): void
    {
        $service = new GitlabReportService([
            'redacted-fields' => ['password'],
        ]);

        $redact = (new \ReflectionClass($service))->getMethod('redact');
        $redact->setAccessible(true);

        // Non-redacted scalar values must keep their original type/value.
        $this->assertSame(true, $redact->invoke($service, 'active', true));
        $this->assertSame(42, $redact->invoke($service, 'count', 42));
        $this->assertNull($redact->invoke($service, 'deleted_at', null));
    }
}
