<?php

namespace Wyox\GitlabReport\Tests\Feature;

use Exception;
use InvalidArgumentException;
use RuntimeException;
use Wyox\GitlabReport\GitlabReportService;
use Wyox\GitlabReport\Tests\TestCase;

class ExceptionIgnoringTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('gitlab-report.ignore-exceptions', [
            InvalidArgumentException::class,
            RuntimeException::class,
        ]);
    }

    public function test_ignored_exception_classes_from_config(): void
    {
        $config = $this->app['config']->get('gitlab-report');

        $this->assertContains(InvalidArgumentException::class, $config['ignore-exceptions']);
        $this->assertContains(RuntimeException::class, $config['ignore-exceptions']);
    }
}
