<?php

namespace Wyox\GitlabReport;

// Use default Request facade
use Gitlab\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;
use Wyox\GitlabReport\Incidents\CommandIncident;
use Wyox\GitlabReport\Incidents\Incident;
use Wyox\GitlabReport\Incidents\RequestIncident;

class GitlabReportService
{
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
     */
    public function __construct(array $config = [])
    {
        if (!empty($config['token']) && !empty($config['url'])) {
            $this->client = new Client();
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
                    ? new CommandIncident($exception, $_SERVER['argv'])
                    : new RequestIncident($exception, $this->redactRequest($this->request()));

                // Report incident
                $this->reportIncident($incident);
            }
        } catch (Throwable $e) {
            if ($this->config['debug']) {
                dump($e);
            }
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
     * Hides any sensitive information in a request object.
     *
     * @param Request $request
     *
     * @return Request
     */
    private function redactRequest(Request $request): Request
    {
        $request->query->replace($this->redactArray($request->query->all()));
        $request->request->replace($this->redactArray($request->request->all()));

        if ($request->hasSession()) {
            $request->session()->replace($this->redactArray($request->session()->all()));
        }

        return $request;
    }

    /**
     * Redacts an array (recursive loop).
     *
     * @param $array
     *
     * @return mixed
     */
    private function redactArray($array): mixed
    {
        foreach ($array as $key => $value) {
            if (is_array($array[$key])) {
                $array[$key] = $this->redactArray($array[$key]);
            }

            if (is_string($array[$key]) || is_bool($array[$key]) || is_numeric($array[$key]) || is_null($array[$key])) {
                $array[$key] = $this->redact($key, $value);
            }
        }

        return $array;
    }

    /**
     * Simple redact function.
     *
     * @param $key
     * @param $value
     *
     * @return string
     */
    private function redact($key, $value): string
    {
        // Redact is the field name is in redacted-fields
        return in_array($key, $this->config['redacted-fields'], true)
            ? '[redacted]'
            : $value;
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
     * Internal report incident
     * @param Incident $incident
     * @return void
     */
    private function reportIncident(Incident $incident): void
    {
        // Check if the incident exists in our Gitlab environment
        if (!$this->incidentExists($incident)) {
            $this->reportToGitlab($incident);
        }
    }

    /**
     * Check if incident exists in Gitlab
     * @param Incident $incident
     * @return bool
     */
    private function incidentExists(Incident $incident): bool
    {
        if (Cache::has('gitlab_reporter_' . $incident->hash()) && $this->config['cache']) {
            return true;
        }

        // Search for issues in gitlab with the exact unique hash from the incident
        $issues = $this->client->issues()->all($this->config['project_id'], [
            'search' => $incident->hash(),
            'state' => 'opened',
        ]);

        $exists = !empty($issues);

        // Found it, lets cache it so we don't have to search it again...
        if ($exists && $this->config['cache']) {
            Cache::put('gitlab_reporter_' . $incident->hash(), true, 60 * 15);
        }


        return $exists;
    }

    /**
     * Create issue in Gitlab
     * @param Incident $incident
     * @return void
     */
    private function reportToGitlab(Incident $incident): void
    {
        $labels = $this->labels;
        $this->client->issues()->create(
            $this->config['project_id'],
            [
                'title' => $incident->title(),
                'description' => $incident->markdown(),
                'issue_type' => 'incident',
                'labels' => is_array($labels) ? implode(',', $labels) : $labels,
            ]
        );

        if ($this->config['cache']) {
            Cache::put('gitlab_reporter_' . $incident->hash(), true, 60 * 15);
        }
    }
}
