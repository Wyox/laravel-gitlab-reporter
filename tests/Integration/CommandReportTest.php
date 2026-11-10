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
        $_SERVER['argv'] = ['artisan', 'import:users', '--force'];
    }

    protected function tearDown(): void
    {
        $_SERVER['argv'] = $this->argv;

        parent::tearDown();
    }

    public function test_console_exception_creates_command_issue(): void
    {
        $this->expectNewIssue();

        report(new RuntimeException('Import failed'));

        $issue = $this->createdIssue();
        $this->assertSame('BUG: Import failed', $issue['title']);
        $this->assertStringContainsString('| Message   | Import failed |', $issue['description']);
        $this->assertStringContainsString('| Command   | `artisan` |', $issue['description']);
        $this->assertStringContainsString('| Arguments  | `import:users --force` |', $issue['description']);
    }

    public function test_query_exception_includes_sql_with_bindings(): void
    {
        $this->expectNewIssue();

        try {
            DB::select('select * from missing_table where id = ?', [5]);
        } catch (QueryException $e) {
            report($e);
        }

        $this->assertStringContainsString('select * from missing_table where id = 5', $this->createdIssue()['description']);
    }
}
