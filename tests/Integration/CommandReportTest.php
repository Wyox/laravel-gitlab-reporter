<?php

namespace Wyox\GitlabReport\Tests\Integration;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CommandReportTest extends TestCase
{
    protected bool $runningInConsole = true;

    private array $argv;

    protected function setUp(): void
    {
        parent::setUp();

        $this->argv = $_SERVER['argv'];
    }

    protected function tearDown(): void
    {
        $_SERVER['argv'] = $this->argv;

        parent::tearDown();
    }

    // Secrets are random so they can't show up through the source excerpt of this file in the "Code" section.
    private function secret(): string
    {
        return bin2hex(random_bytes(8));
    }

    public function test_console_exception_creates_command_issue_with_redacted_options(): void
    {
        [$password, $token] = [$this->secret(), $this->secret()];
        $_SERVER['argv'] = ['artisan', 'import:users', '--force', "--password={$password}", '--token', $token];
        $this->expectNewIssue();

        report(new RuntimeException('Import failed'));

        $issue = $this->createdIssue();
        $this->assertSame('BUG: `Import failed`', $issue['title']);
        $this->assertStringContainsString('| Message   | `Import failed` |', $issue['description']);
        $this->assertStringContainsString('| Command   | `artisan` |', $issue['description']);
        $this->assertStringContainsString('| Arguments  | `import:users --force --password=[redacted] --token [redacted]` |', $issue['description']);
        $this->assertStringNotContainsString($password, $issue['description']);
        $this->assertStringNotContainsString($token, $issue['description']);
    }

    public function test_query_exception_leaves_out_bindings(): void
    {
        $_SERVER['argv'] = ['artisan', 'import:users'];
        $this->expectNewIssue();
        $secret = $this->secret();

        $this->reportFailingQuery([$secret]);

        $issue = $this->createdIssue();
        $this->assertStringContainsString('select * from missing_table where email = ?', $issue['description']);
        $this->assertStringContainsString('where email = ?', $issue['title']);
        $this->assertStringNotContainsString($secret, $issue['title']);
        $this->assertStringNotContainsString($secret, $issue['description']);
    }

    public function test_query_bindings_can_be_switched_on(): void
    {
        config()->set('gitlab-report.sql-bindings', true);
        $_SERVER['argv'] = ['artisan', 'import:users'];
        $this->expectNewIssue();
        $secret = $this->secret();

        $this->reportFailingQuery([$secret]);

        $this->assertStringContainsString("select * from missing_table where email = {$secret}", $this->createdIssue()['description']);
    }

    public function test_bindings_cannot_close_the_sql_code_block(): void
    {
        config()->set('gitlab-report.sql-bindings', true);
        $_SERVER['argv'] = ['artisan', 'import:users'];
        $this->expectNewIssue();

        $this->reportFailingQuery(["x\n```\n/label ~injected"]);

        $this->assertStringContainsString("#### SQL\n\n````sql\n", $this->createdIssue()['description']);
    }

    public function test_carriage_returns_are_normalised_in_reports(): void
    {
        config()->set('gitlab-report.sql-bindings', true);
        $_SERVER['argv'] = ['artisan', 'import:users'];
        $this->expectNewIssue();

        $this->reportFailingQuery(["a\r\nb\rc"]);

        $this->assertStringNotContainsString("\r", $this->createdIssue()['description']);
    }

    private function reportFailingQuery(array $bindings): void
    {
        try {
            DB::select('select * from missing_table where email = ?', $bindings);
        } catch (QueryException $e) {
            report($e);
        }
    }
}
