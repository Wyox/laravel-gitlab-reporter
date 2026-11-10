<?php

namespace Wyox\GitlabReport\Tests\Integration;

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Console\Exception\CommandNotFoundException;

class RequestReportTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->match(['GET', 'POST'], '/boom', fn () => throw new RuntimeException('Something broke'));
        $router->get('/session', fn () => throw new RuntimeException('Session broke'))->middleware('web');
        $router->get('/users/{id}/boom', fn () => throw new RuntimeException('User broke'));
        $router->post('/report-and-continue', function (Request $request) {
            report(new RuntimeException('Handled'));

            return [
                'password' => $request->input('password'),
                'token' => $request->query('token'),
                'session' => session('password_hash_web'),
            ];
        })->middleware(StartSession::class);
    }

    // Secrets are random so they can't show up through the source excerpt of this file in the "Code" section.
    private function secret(): string
    {
        return bin2hex(random_bytes(8));
    }

    public function test_request_exception_creates_issue(): void
    {
        $this->expectNewIssue();
        $secret = $this->secret();

        $this->get("/boom?page=2&password={$secret}")->assertStatus(500);

        $this->assertCount(2, $this->history);
        $search = $this->history[0]['request'];
        $this->assertSame('GET', $search->getMethod());
        parse_str($search->getUri()->getQuery(), $query);
        $this->assertSame('opened', $query['state']);
        $this->assertSame('created-by-me', $query['scope']);

        $issue = $this->createdIssue();
        $this->assertSame('BUG: `Something broke`', $issue['title']);
        $this->assertSame('incident', $issue['issue_type']);
        $this->assertSame('bug,production', $issue['labels']);

        $description = $issue['description'];
        $this->assertStringContainsString("Identifier: `{$query['search']}`", $description);
        $this->assertStringContainsString('| Message   | `Something broke` |', $description);
        $this->assertStringContainsString('| Method    | GET |', $description);
        $this->assertStringContainsString('| Path      | `/boom` |', $description);
        $this->assertStringContainsString('| URL       | `http://localhost/boom?page=2&password=%5Bredacted%5D` |', $description);
        $this->assertStringContainsString('"page" => "2"', $description);
        $this->assertStringContainsString('"password" => "[redacted]"', $description);
        $this->assertStringNotContainsString($secret, $description);
    }

    public function test_post_data_with_nulls_and_nesting_is_reported_and_redacted(): void
    {
        $this->expectNewIssue();
        $secret = $this->secret();

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

    public function test_session_data_is_included_and_laravel_secrets_are_redacted(): void
    {
        $this->expectNewIssue();
        $secrets = [$this->secret(), $this->secret(), $this->secret()];

        $this->withSession([
            'cart' => 3,
            'password' => $secrets[0],
            'password_hash_web' => $secrets[1],
            '_token' => $secrets[2],
        ])->get('/session')->assertStatus(500);

        $description = $this->createdIssue()['description'];
        $this->assertStringContainsString("| URL       | `http://localhost/session` |\n", $description);
        $this->assertStringContainsString('"cart" =>', $description);
        foreach ($secrets as $secret) {
            $this->assertStringNotContainsString($secret, $description);
        }
    }

    public function test_reporting_leaves_the_live_request_and_session_untouched(): void
    {
        $this->expectNewIssue();
        [$password, $token, $hash] = [$this->secret(), $this->secret(), $this->secret()];

        $this->withSession(['password_hash_web' => $hash])
            ->post("/report-and-continue?token={$token}", ['password' => $password])
            ->assertOk()
            ->assertExactJson(['password' => $password, 'token' => $token, 'session' => $hash]);

        $description = $this->createdIssue()['description'];
        $this->assertStringContainsString('"password" => "[redacted]"', $description);
        foreach ([$password, $token, $hash] as $secret) {
            $this->assertStringNotContainsString($secret, $description);
        }
    }

    public function test_user_block_only_shows_allow_listed_attributes(): void
    {
        $this->expectNewIssue();
        [$password, $rememberToken] = [$this->secret(), $this->secret()];
        $user = (new User())->forceFill([
            'id' => 5,
            'email' => 'ivo@example.com',
            'password' => $password,
            'remember_token' => $rememberToken,
        ]);

        $this->actingAs($user)->get('/boom')->assertStatus(500);

        $description = $this->createdIssue()['description'];
        $this->assertStringContainsString('| Authenticated | Yes |', $description);
        $this->assertStringContainsString('"id" => 5', $description);
        $this->assertStringNotContainsString('ivo@example.com', $description);
        $this->assertStringNotContainsString($password, $description);
        $this->assertStringNotContainsString($rememberToken, $description);
    }

    public function test_built_in_redacted_fields_still_apply_when_the_config_lists_others(): void
    {
        config()->set('gitlab-report.redacted-fields', ['iban']);
        $this->expectNewIssue();
        [$token, $iban] = [$this->secret(), $this->secret()];

        $this->get("/boom?access_token={$token}&IBAN={$iban}")->assertStatus(500);

        $description = $this->createdIssue()['description'];
        $this->assertStringNotContainsString($token, $description);
        $this->assertStringNotContainsString($iban, $description);
    }

    public function test_secret_names_are_matched_regardless_of_case_and_separators(): void
    {
        $this->expectNewIssue();
        $secrets = [$this->secret(), $this->secret(), $this->secret(), $this->secret()];

        $this->get("/boom?api_token={$secrets[0]}&accessToken={$secrets[1]}&signature={$secrets[2]}&X-Auth-Token={$secrets[3]}")
            ->assertStatus(500);

        $description = $this->createdIssue()['description'];
        foreach ($secrets as $secret) {
            $this->assertStringNotContainsString($secret, $description);
        }
    }

    public function test_query_strings_inside_url_values_are_redacted(): void
    {
        $this->expectNewIssue();
        $secret = $this->secret();

        $this->withSession(['url' => ['intended' => "https://app.test/reset?token={$secret}&page=2#top"]])
            ->get('/session')
            ->assertStatus(500);

        $description = $this->createdIssue()['description'];
        $this->assertStringContainsString('"intended" => "https://app.test/reset?token=%5Bredacted%5D&page=2#top"', $description);
        $this->assertStringNotContainsString($secret, $description);
    }

    public function test_invalid_utf8_from_the_client_does_not_lose_the_report(): void
    {
        $this->expectNewIssue();

        $this->withHeader('User-Agent', "Browser \xff\xfe")->get('/boom')->assertStatus(500);

        $this->assertStringContainsString('| User agent | `Browser ??` |', $this->createdIssue()['description']);
    }

    public function test_title_keeps_mentions_and_references_in_a_code_span(): void
    {
        $this->expectNewIssue();

        report(new RuntimeException('Ping @all about #1 and `x`'));

        $this->assertSame("BUG: `Ping @all about #1 and 'x'`", $this->createdIssue()['title']);
    }

    public function test_requests_on_the_same_route_share_one_issue(): void
    {
        $this->expectNewIssue();

        $this->get('/users/1/boom')->assertStatus(500);
        $this->get('/users/2/boom')->assertStatus(500);
        $this->get('http://other-host.example/users/3/boom')->assertStatus(500);

        $this->assertCount(2, $this->history);
    }

    public function test_rate_limit_stops_gitlab_lookups_for_a_flood_of_new_errors(): void
    {
        config()->set('gitlab-report.rate-limit', 1);
        $this->expectNewIssue();
        Log::spy();

        $this->get('/boom')->assertStatus(500);
        $this->get('/users/1/boom')->assertStatus(500);
        $this->get('/users/1/boom')->assertStatus(500);

        $this->assertCount(2, $this->history);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message) => str_contains($message, 'rate limit'));
    }

    public function test_client_controlled_values_cannot_inject_markdown(): void
    {
        $this->expectNewIssue();

        $this->withHeader('User-Agent', '![x](https://attacker.example/p.png) | @all')->get('/boom')->assertStatus(500);

        $description = $this->createdIssue()['description'];
        $this->assertStringContainsString('| User agent | `![x](https://attacker.example/p.png) \| @all` |', $description);
    }

    public function test_trace_leaves_out_call_arguments(): void
    {
        $this->expectNewIssue();
        $secret = $this->secret();

        try {
            $this->failWith($secret);
        } catch (RuntimeException $e) {
            report($e);
        }

        $description = $this->createdIssue()['description'];
        $this->assertStringContainsString('->failWith()', $description);
        $this->assertStringNotContainsString($secret, $description);
    }

    private function failWith(string $secret): void
    {
        throw new RuntimeException('Failed');
    }

    public function test_message_redactor_is_applied_to_title_and_description(): void
    {
        config()->set('gitlab-report.message-redactor', fn (string $message) => preg_replace('/[0-9a-f]{16}/', '[redacted]', $message));
        $this->expectNewIssue();
        $secret = $this->secret();

        report(new RuntimeException("Token {$secret} was rejected"));

        $issue = $this->createdIssue();
        $this->assertSame('BUG: `Token [redacted] was rejected`', $issue['title']);
        $this->assertStringNotContainsString($secret, $issue['description']);
    }

    public function test_context_rows_and_code_excerpt_can_be_switched_off(): void
    {
        config()->set('gitlab-report.context-host', false);
        config()->set('gitlab-report.context-client-ip', false);
        config()->set('gitlab-report.context-user-agent', false);
        config()->set('gitlab-report.code-excerpt', false);
        $this->expectNewIssue();

        $this->withHeader('User-Agent', 'Browser')->get('/boom')->assertStatus(500);

        $description = $this->createdIssue()['description'];
        $this->assertStringContainsString('#### Context', $description);
        $this->assertStringNotContainsString('| Host |', $description);
        $this->assertStringNotContainsString('| Client IP |', $description);
        $this->assertStringNotContainsString('| User agent |', $description);
        $this->assertStringNotContainsString('#### Code', $description);
    }

    public function test_existing_open_issue_gets_a_recurrence_note_instead_of_a_duplicate(): void
    {
        $this->respondWithOpenIssue(7);
        $this->respond(201, ['id' => 1]);

        $this->get('/boom')->assertStatus(500);

        $this->assertCount(2, $this->history);
        $note = $this->history[1]['request'];
        $this->assertSame('POST', $note->getMethod());
        $this->assertSame('/api/v4/projects/42/issues/7/notes', $note->getUri()->getPath());
    }

    public function test_search_result_without_the_exact_identifier_is_not_treated_as_the_open_issue(): void
    {
        $this->respond(200, [['id' => 7, 'iid' => 7, 'description' => 'Unrelated issue']]);
        $this->respond(201, ['id' => 1, 'iid' => 1]);

        $this->get('/boom')->assertStatus(500);

        $this->createdIssue();
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
        Log::spy();

        $this->get('/boom')->assertStatus(500);

        $this->assertCount(1, $this->history);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message) => str_contains($message, 'could not report'));
    }

    public function test_huge_multibyte_message_fits_gitlab_limits(): void
    {
        $this->expectNewIssue();

        report(new RuntimeException(str_repeat('ü', 600000)));

        $this->createdIssue();
    }
}
