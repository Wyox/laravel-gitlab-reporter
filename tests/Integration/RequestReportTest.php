<?php

namespace Wyox\GitlabReport\Tests\Integration;

use RuntimeException;
use Symfony\Component\Console\Exception\CommandNotFoundException;

class RequestReportTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->match(['GET', 'POST'], '/boom', fn () => throw new RuntimeException('Something broke'));
        $router->get('/session', fn () => throw new RuntimeException('Session broke'))->middleware('web');
    }

    public function test_request_exception_creates_issue(): void
    {
        $this->expectNewIssue();
        // Random so it can't show up through the source excerpt of this file in the "Code" section.
        $secret = bin2hex(random_bytes(8));

        $this->get("/boom?page=2&password={$secret}")->assertStatus(500);

        $this->assertCount(2, $this->history);
        $search = $this->history[0]['request'];
        $this->assertSame('GET', $search->getMethod());
        parse_str($search->getUri()->getQuery(), $query);
        $this->assertSame('opened', $query['state']);

        $issue = $this->createdIssue();
        $this->assertSame('BUG: Something broke', $issue['title']);
        $this->assertSame('incident', $issue['issue_type']);
        $this->assertSame('bug,production', $issue['labels']);

        $description = $issue['description'];
        $this->assertStringContainsString("Identifier: `{$query['search']}`", $description);
        $this->assertStringContainsString('| Message   | Something broke |', $description);
        $this->assertStringContainsString('| Method    | GET |', $description);
        $this->assertStringContainsString('| Path      | /boom |', $description);
        $this->assertStringContainsString('| URL       | http://localhost/boom?page=2&password=%5Bredacted%5D |', $description);
        $this->assertStringContainsString('"page" => "2"', $description);
        $this->assertStringContainsString('"password" => "[redacted]"', $description);
        $this->assertStringNotContainsString($secret, $description);
    }

    public function test_post_data_with_nulls_and_nesting_is_reported_and_redacted(): void
    {
        $this->expectNewIssue();
        $secret = bin2hex(random_bytes(8));

        $this->postJson('/boom', [
            'name' => 'Ivo',
            'comment' => null,
            'account' => ['password' => $secret, 'active' => true],
        ])->assertStatus(500);

        $description = $this->createdIssue()['description'];
        $this->assertStringContainsString('| Method    | POST |', $description);
        $this->assertStringContainsString('"name" => "Ivo"', $description);
        $this->assertStringNotContainsString($secret, $description);
    }

    public function test_session_data_is_included_and_redacted(): void
    {
        $this->expectNewIssue();
        $secret = bin2hex(random_bytes(8));

        $this->withSession(['cart' => 3, 'password' => $secret])->get('/session')->assertStatus(500);

        $description = $this->createdIssue()['description'];
        $this->assertStringContainsString("| URL       | http://localhost/session |\n", $description);
        $this->assertStringContainsString('"cart" =>', $description);
        $this->assertStringNotContainsString($secret, $description);
    }

    public function test_existing_open_issue_gets_a_recurrence_note_instead_of_a_duplicate(): void
    {
        $this->respond(200, [['id' => 7, 'iid' => 7, 'title' => 'BUG: Something broke']]);
        $this->respond(201, ['id' => 1]);

        $this->get('/boom')->assertStatus(500);

        $this->assertCount(2, $this->history);
        $note = $this->history[1]['request'];
        $this->assertSame('POST', $note->getMethod());
        $this->assertSame('/api/v4/projects/42/issues/7/notes', $note->getUri()->getPath());
    }

    public function test_cache_skips_gitlab_for_a_repeated_exception(): void
    {
        $this->expectNewIssue();

        $this->get('/boom');
        $this->get('/boom');

        $this->assertCount(2, $this->history);
    }

    public function test_ignored_exceptions_are_not_sent(): void
    {
        report(new CommandNotFoundException('nope'));

        $this->assertCount(0, $this->history);
    }

    public function test_gitlab_being_down_does_not_break_the_application(): void
    {
        $this->respond(500, ['message' => '500 Internal Server Error']);

        $this->get('/boom')->assertStatus(500);

        $this->assertCount(1, $this->history);
    }

    public function test_huge_multibyte_message_fits_gitlab_limits(): void
    {
        $this->expectNewIssue();

        report(new RuntimeException(str_repeat('ü', 600000)));

        $this->createdIssue();
    }
}
