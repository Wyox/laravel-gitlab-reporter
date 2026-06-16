<?php

namespace Wyox\GitlabReport\Tests\Unit;

use Exception;
use Illuminate\Http\Request;
use RuntimeException;
use Wyox\GitlabReport\Components\CommandSummaryComponent;
use Wyox\GitlabReport\Components\RequestComponent;
use Wyox\GitlabReport\Incidents\CommandIncident;
use Wyox\GitlabReport\Incidents\RequestIncident;
use Wyox\GitlabReport\Tests\TestCase;

class ReportContentTest extends TestCase
{
    public function test_query_params_are_rendered_in_request_report(): void
    {
        $request = Request::create('/search?term=laravel&page=2', 'GET');
        $incident = new RequestIncident(new Exception('Boom'), $request);

        // Render the request component in isolation so the assertion is not
        // polluted by the code-excerpt section (which echoes this test's source).
        $output = (new RequestComponent($incident))->render();

        $this->assertStringContainsString('#### Query params', $output);
        $this->assertStringContainsString('term', $output);
        $this->assertStringContainsString('laravel', $output);
        $this->assertStringNotContainsString('*No Query parameters*', $output);
    }

    public function test_empty_query_params_show_placeholder(): void
    {
        $request = Request::create('/no-query', 'GET');
        $incident = new RequestIncident(new Exception('Boom'), $request);

        $output = (new RequestComponent($incident))->render();

        $this->assertStringContainsString('*No Query parameters*', $output);
    }

    public function test_command_report_interpolates_message(): void
    {
        $incident = new CommandIncident(new Exception('Disk is not full'), ['artisan', 'backup:run']);

        // Render the summary component in isolation to avoid the code-excerpt
        // section echoing this file's source into the assertion target.
        $summary = (new CommandSummaryComponent($incident))->render();

        $this->assertStringContainsString('Disk is not full', $summary);
        // The pre-3.0 template leaked the literal placeholder.
        $this->assertStringNotContainsString('{ $message }', $summary);
        $this->assertStringNotContainsString('$message', $summary);
    }

    public function test_previous_exception_chain_is_rendered(): void
    {
        $root = new RuntimeException('Root cause: connection refused');
        $wrapper = new Exception('Outer failure', 0, $root);

        $incident = new CommandIncident($wrapper, ['artisan', 'queue:work']);
        $markdown = $incident->markdown();

        $this->assertStringContainsString('Caused by:', $markdown);
        $this->assertStringContainsString('Root cause: connection refused', $markdown);
        $this->assertStringContainsString(RuntimeException::class, $markdown);
    }

    public function test_code_excerpt_is_included(): void
    {
        $incident = new CommandIncident(new Exception('Boom'), ['artisan', 'test']);
        $markdown = $incident->markdown();

        $this->assertStringContainsString('#### Code', $markdown);
    }

    public function test_context_section_is_included(): void
    {
        $request = Request::create('/dashboard', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'PHPUnit']);
        $incident = new RequestIncident(new Exception('Boom'), $request);

        $markdown = $incident->markdown();

        $this->assertStringContainsString('#### Context', $markdown);
        $this->assertStringContainsString('PHP', $markdown);
        $this->assertStringContainsString('Environment', $markdown);
    }
}
