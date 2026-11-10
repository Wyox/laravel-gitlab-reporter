<?php

namespace Wyox\GitlabReport\Tests\Integration;

use Gitlab\Client;
use Gitlab\HttpClient\Builder;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Orchestra\Testbench\TestCase as Orchestra;
use Throwable;
use Wyox\GitlabReport\ServiceProvider;

/**
 * Boots a real Laravel app with the package installed. Only the HTTP layer towards GitLab is faked:
 * queue responses with respond(), inspect what was sent through $history.
 */
abstract class TestCase extends Orchestra
{
    /** Laravel caches runningInConsole() on first use, so this has to be decided before the app boots. */
    protected bool $runningInConsole = false;

    protected MockHandler $gitlab;

    /** @var array<int, array{request: \Psr\Http\Message\RequestInterface}> */
    protected array $history = [];

    protected function setUp(): void
    {
        $_SERVER['APP_RUNNING_IN_CONSOLE'] = $this->runningInConsole ? 'true' : 'false';

        parent::setUp();

        // What the README tells users to add to their exception handler.
        $this->app->make(ExceptionHandler::class)->reportable(fn (Throwable $e) => app('gitlab.report')->report($e));
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($_SERVER['APP_RUNNING_IN_CONSOLE']);
    }

    protected function getPackageProviders($app): array
    {
        return [ServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('k', 32)));
        // Fresh per test, so the issue cache and the rate limiter never carry over between tests.
        $app['config']->set('cache.default', 'array');
        $app['config']->set('gitlab-report.url', 'https://gitlab.test');
        $app['config']->set('gitlab-report.token', 'secret-token');
        $app['config']->set('gitlab-report.project_id', 42);
        $app['config']->set('gitlab-report.labels', 'bug,production');
        $app['config']->set('gitlab-report.cache', true);
        $app['config']->set('gitlab-report.debug', false);

        $this->gitlab = new MockHandler();
        $stack = HandlerStack::create($this->gitlab);
        $stack->push(Middleware::history($this->history));

        $app->instance(Client::class, new Client(new Builder(new Guzzle(['handler' => $stack]))));
    }

    protected function respond(int $status, mixed $body = []): void
    {
        $this->gitlab->append(new Response($status, ['Content-Type' => 'application/json'], json_encode($body)));
    }

    /** Answer the issue search with an open issue whose description carries the identifier that was searched for. */
    protected function respondWithOpenIssue(int $iid): void
    {
        $this->gitlab->append(function (\Psr\Http\Message\RequestInterface $request) use ($iid) {
            parse_str($request->getUri()->getQuery(), $query);
            $issue = ['id' => $iid, 'iid' => $iid, 'description' => "Identifier: `{$query['search']}`"];

            return new Response(200, ['Content-Type' => 'application/json'], json_encode([$issue]));
        });
    }

    /** Queue the happy path: no open issue with this hash yet, then a successful create. */
    protected function expectNewIssue(): void
    {
        $this->respond(200, []);
        $this->respond(201, ['id' => 1, 'iid' => 1]);
    }

    /**
     * The payload of the single issue-create call, after asserting GitLab would accept it.
     */
    protected function createdIssue(): array
    {
        $posts = array_values(array_filter($this->history, fn ($t) => $t['request']->getMethod() === 'POST'));
        $this->assertCount(1, $posts, 'Expected exactly one issue to be created');

        $request = $posts[0]['request'];
        $this->assertSame('/api/v4/projects/42/issues', $request->getUri()->getPath());
        $this->assertSame('secret-token', $request->getHeaderLine('PRIVATE-TOKEN'));
        $this->assertStringContainsString('application/json', $request->getHeaderLine('Content-Type'));

        $issue = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);

        // Limits GitLab enforces on issues.
        $this->assertNotSame('', $issue['title']);
        $this->assertLessThanOrEqual(255, mb_strlen($issue['title']), 'GitLab rejects titles over 255 characters');
        $this->assertLessThanOrEqual(1048576, mb_strlen($issue['description']), 'GitLab rejects descriptions over 1048576 characters');
        $this->assertTrue(mb_check_encoding($issue['title'], 'UTF-8'), 'Title is not valid UTF-8');
        $this->assertTrue(mb_check_encoding($issue['description'], 'UTF-8'), 'Description is not valid UTF-8');

        return $issue;
    }
}
