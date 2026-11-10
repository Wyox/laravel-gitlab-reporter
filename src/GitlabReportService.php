<?php

namespace Wyox\GitlabReport;

// Use default Request facade
use Gitlab\Client;
use Gitlab\HttpClient\Builder;
use Http\Client\Common\Plugin\RedirectPlugin;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;
use Wyox\GitlabReport\Incidents\CommandIncident;
use Wyox\GitlabReport\Incidents\Incident;
use Wyox\GitlabReport\Incidents\RequestIncident;

class GitlabReportService
{
    /**
     * Field names that are always redacted, on top of the configured `redacted-fields`. `*` is a wildcard. Keys and
     * patterns are compared case-insensitively with `_`, `-`, `.` and spaces removed, so `*token*` also covers
     * `api_token`, `X-Auth-Token` and `accessToken`. Includes Laravel's own secrets: the CSRF token (`_token`) and the
     * password hash AuthenticateSession keeps in the session (`password_hash_<guard>`).
     */
    public const REDACTED_FIELDS = [
        '*password*',
        '*passwd*',
        'pass',
        'pwd',
        '*secret*',
        '*token*',
        '*api_key*',
        'key',
        'auth',
        'authorization',
        '*credential*',
        'signature',
        'code',
        'otp',
        'pin',
        'jwt',
        'cookie',
        '*private_key*',
        '*two_factor*',
        '*recovery_code*',
        '*credit_card*',
        '*card_number*',
        'cvv',
        'cvc',
        'ssn',
    ];

    /**
     * @var Client $client
     */
    private Client $client;

    /**
     * @var array Configuration for the reporter
     */
    private array $config;

    /**
     * @var string Contains all the labels applied to an issue
     */
    private string $labels;


    /**
     * GitlabReportService constructor.
     *
     * @param $config
     * @param Client|null $client Pre-built client, e.g. with a mocked HTTP layer in tests
     */
    public function __construct(array $config = [], ?Client $client = null)
    {
        if (!empty($config['token']) && !empty($config['url'])) {
            $this->client = $client ?? self::client(new Builder());
            $this->client->setUrl($config['url']);
            $this->client->authenticate($config['token'], Client::AUTH_HTTP_TOKEN);
        }

        $this->labels = !empty($config['labels']) ? $config['labels'] : '';
        $this->config = $config;

        return $this;
    }

    /**
     * GitlabReport function to report exceptions.
     * This will generate a GitlabReport and send it to GitLab as issue under the project.
     *
     * @param Throwable $exception
     *
     * @throws Throwable
     */
    public function report(Throwable $exception): void
    {

        try {
            // Can we report this exception?
            if ($this->canReport($exception)) {
                /**
                 * @var Incident $incident
                 */
                $incident = app()->runningInConsole()
                    ? new CommandIncident($exception, $this->redactArgv($_SERVER['argv'] ?? []))
                    : $this->requestIncident($exception, $this->request());

                // Report incident
                $this->reportIncident($incident);
            }
        } catch (Throwable $e) {
            $this->logFailure($e);
        }
    }

    /**
     * A Gitlab\Client without the redirect plugin: it re-sends the PRIVATE-TOKEN header (and on 307/308 the issue)
     * to whatever host a redirect points at, including plain http.
     */
    public static function client(Builder $builder): Client
    {
        $client = new Client($builder);
        $builder->removePlugin(RedirectPlugin::class);

        return $client;
    }

    /**
     * Reporting runs inside the application's exception handling, so a failure is logged rather than thrown. Only
     * the exception's class and message are logged unless `debug` is on; nothing is written to the response.
     */
    private function logFailure(Throwable $e): void
    {
        try {
            Log::warning('GitLab reporter could not report an exception.', !empty($this->config['debug'])
                ? ['exception' => $e]
                : ['error' => get_class($e) . ': ' . $e->getMessage()]);
        } catch (Throwable) {
            // A broken log channel must not break the application that is already handling an exception.
        }
    }

    /**
     * Checks if the exception should be reported to Gitlab.
     *
     * @param Throwable $exception
     *
     * @return bool
     */
    private function canReport(Throwable $exception)
    {
        return !$this->isIgnored($exception);
    }

    /**
     * Returns if the exception is ignored based on the configuration.
     *
     * @param Throwable $exception
     *
     * @return bool
     */
    private function isIgnored(Throwable $exception)
    {
        $ignored = array_filter(
            $this->config['ignore-exceptions'] ?? [],
            function ($class) use ($exception) {
                return is_a($exception, $class);
            }
        );

        return count($ignored) > 0;
    }

    /**
     * Builds the incident from redacted copies of the request data. The live request and session are never
     * modified: the application keeps running (and saves its session) after reporting.
     */
    private function requestIncident(Throwable $exception, Request $request): RequestIncident
    {
        $user = RequestIncident::userAttributes($request, $this->config['user-fields'] ?? ['id']);

        return new RequestIncident(
            $exception,
            $request,
            $this->redactArray($request->query->all()),
            $this->redactArray($request->all()),
            $request->hasSession() ? $this->redactArray($request->session()->all()) : [],
            $user === null ? null : $this->redactArray($user),
        );
    }

    /**
     * Redacts `--key=value` and `--key value` console options whose key is a redacted field.
     */
    private function redactArgv(array $argv): array
    {
        $argv = array_values($argv);
        $redactNext = false;

        foreach ($argv as $i => $arg) {
            $arg = (string) $arg;

            if ($redactNext && !str_starts_with($arg, '-')) {
                $argv[$i] = '[redacted]';
                $redactNext = false;
                continue;
            }

            $redactNext = false;

            if (preg_match('/^(--?)([^=]+)(=.*)?$/s', $arg, $matches) && $this->isRedacted($matches[2])) {
                if (isset($matches[3])) {
                    $argv[$i] = "{$matches[1]}{$matches[2]}=[redacted]";
                } else {
                    $redactNext = true;
                }
            }
        }

        return $argv;
    }

    /**
     * Redacts an array (recursive loop). A matching key redacts its whole value, nested arrays included. String values
     * that are URLs get their query parameters redacted the same way.
     */
    private function redactArray(array $array): array
    {
        foreach ($array as $key => $value) {
            $array[$key] = match (true) {
                $this->isRedacted($key) => '[redacted]',
                is_array($value) => $this->redactArray($value),
                is_string($value) => $this->redactUrl($value),
                default => $value,
            };
        }

        return $array;
    }

    /**
     * Redacts the query parameters of a URL value, such as the `_previous.url` and `url.intended` Laravel keeps in the
     * session. Values that aren't URLs, or whose query has nothing to redact, are returned unchanged.
     */
    private function redactUrl(string $value): string
    {
        if (!preg_match('#^https?://[^?\s]*\?#i', $value)) {
            return $value;
        }

        [$url, $rest] = explode('?', $value, 2);
        [$query, $fragment] = array_pad(explode('#', $rest, 2), 2, null);
        parse_str($query, $params);
        $redacted = $this->redactArray($params);

        if ($redacted === $params) {
            return $value;
        }

        return $url . '?' . Arr::query($redacted) . ($fragment === null ? '' : "#{$fragment}");
    }

    private function redact($key, $value): mixed
    {
        return $this->isRedacted($key) ? '[redacted]' : $value;
    }

    private function isRedacted(int|string $key): bool
    {
        $key = self::normalizeKey((string) $key);

        foreach ([...self::REDACTED_FIELDS, ...($this->config['redacted-fields'] ?? [])] as $pattern) {
            if (Str::is(self::normalizeKey($pattern), $key)) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeKey(string $key): string
    {
        return str_replace(['_', '-', '.', ' '], '', strtolower($key));
    }

    /**
     * Global limit on GitLab lookups for incidents that aren't cached yet, so a flood of distinct errors can't
     * hammer GitLab or slow down every failing request.
     */
    private function withinRateLimit(): bool
    {
        $max = $this->config['rate-limit'] ?? null;

        if (empty($max)) {
            return true;
        }

        if (RateLimiter::tooManyAttempts('gitlab-report', (int) $max)) {
            return false;
        }

        RateLimiter::hit('gitlab-report', 60);

        return true;
    }

    /**
     * Returns the current Request.
     *
     * @return Request
     */
    private function request(): Request
    {
        return app(Request::class);
    }

    /**
     * Internal report incident.
     *
     * When an open issue for this incident already exists we leave a short
     * "occurred again" note instead of silently dropping the occurrence, so a
     * human can see how often a bug keeps happening. The local cache throttles
     * both creation and these recurrence notes so we never spam GitLab.
     *
     * @param Incident $incident
     * @return void
     */
    private function reportIncident(Incident $incident): void
    {
        $cacheKey = 'gitlab_reporter_' . $incident->hash();

        // Throttled: we already touched GitLab for this incident recently.
        if ($this->config['cache'] && Cache::has($cacheKey)) {
            return;
        }

        if (!$this->withinRateLimit()) {
            // Logged once per minute, so a flood of errors can't silently push genuine reports out.
            if (Cache::add('gitlab_reporter_rate_limited', true, 60)) {
                Log::warning('GitLab reporter rate limit reached, new errors are not reported for the rest of this minute.', [
                    'rate-limit' => $this->config['rate-limit'],
                ]);
            }

            return;
        }

        $existing = $this->findOpenIssue($incident);

        if ($existing === null) {
            $this->createIssue($incident);
        } else {
            $this->commentRecurrence($incident, $existing);
        }
    }

    /**
     * Find an open issue in Gitlab matching the incident's unique hash.
     *
     * @param Incident $incident
     * @return array|null
     */
    private function findOpenIssue(Incident $incident): ?array
    {
        // Only issues this token created, with the exact identifier line: anyone else who can create or edit issues
        // could otherwise quote a hash and capture (or suppress) the reports for that error.
        $issues = $this->client->issues()->all($this->config['project_id'], [
            'search' => $incident->hash(),
            'state' => 'opened',
            'scope' => 'created-by-me',
        ]);
        $identifier = "Identifier: `{$incident->hash()}`";

        foreach ($issues as $issue) {
            if (str_contains((string) ($issue['description'] ?? ''), $identifier)) {
                return $issue;
            }
        }

        return null;
    }

    /**
     * Create issue in Gitlab
     * @param Incident $incident
     * @return void
     */
    private function createIssue(Incident $incident): void
    {
        $labels = $this->labels;
        $issue = $this->client->issues()->create(
            $this->config['project_id'],
            [
                'title' => $incident->title(),
                'description' => $incident->markdown(),
                'issue_type' => 'incident',
                'labels' => is_array($labels) ? implode(',', $labels) : $labels,
            ]
        );

        $this->remember($incident, $issue['iid'] ?? null);
    }

    /**
     * Leave a note on an existing open issue noting that the error happened again.
     *
     * @param Incident $incident
     * @param array $issue
     * @return void
     */
    private function commentRecurrence(Incident $incident, array $issue): void
    {
        $iid = $issue['iid'] ?? null;

        if ($iid !== null) {
            $this->client->issues()->addNote(
                $this->config['project_id'],
                $iid,
                $this->recurrenceNote()
            );
        }

        $this->remember($incident, $iid);
    }

    /**
     * Build the body for a recurrence note.
     *
     * @return string
     */
    private function recurrenceNote(): string
    {
        $environment = function_exists('app') && method_exists(app(), 'environment')
            ? (string) app()->environment()
            : 'unknown';

        return "♻️ This error occurred again at `" . date('c') . "` (environment: `{$environment}`).";
    }

    /**
     * Remember that we touched GitLab for this incident so we throttle the next
     * occurrences within the cache window.
     *
     * @param Incident $incident
     * @param int|null $iid
     * @return void
     */
    private function remember(Incident $incident, ?int $iid): void
    {
        if ($this->config['cache']) {
            Cache::put('gitlab_reporter_' . $incident->hash(), $iid ?? true, 60 * 15);
        }
    }
}
