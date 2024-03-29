<?php

namespace Wyox\GitlabReport;

// Use default Request facade
use Gitlab\Client;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Throwable;
use Wyox\GitlabReport\Reports\DatabaseReport;
use Wyox\GitlabReport\Reports\ExceptionReport;
use Wyox\GitlabReport\Reports\Report;

class GitlabReportService
{
    /**
     * @var Client $client
     */
    private $client;

    /**
     * @var string Configuration for the reporter
     */
    private $config;

    /**
     * @var array Contains all the labels applied to an issue
     */
    private $labels;

    /**
     * @var array
     */
    private $reporters = [
        QueryException::class => DatabaseReport::class,
    ];

    /**
     * GitlabReportService constructor.
     *
     * @param $config
     */
    public function __construct($config)
    {
        if (!empty($config['token'])) {
            $this->client = new Client();
            if (!empty($config['url'])) {
                $this->client->setUrl($config['url']);
            }
            $this->client->authenticate($config['token'], Client::AUTH_HTTP_TOKEN);
        }

        if (!empty($config['labels'])) {
            $this->labels = $config['labels'];
        }

        $this->config = $config;

        return $this;
    }

    /**
     * GitlabReport function to report exceptions.
     * This will generate a GitlabReport and send it to GitLab as issue under the project.
     *
     * @param Throwable $exception
     * @param array|null $labels
     *
     * @throws Throwable
     */
    public function report(Throwable $exception, array $labels = null)
    {
        if ($this->canReport($exception) && !empty($this->client)) {
            try {
                // Get current request
                $request = $this->redactRequest($this->request());

                // Get the proper reporter
                $reporter = $this->reporter($exception);

                /** @var ExceptionReport $report */
                $report = new $reporter($exception, $request);

                // Check if an issue exists with the same title and is currently open.
                $issues = $this->client->issues()->all($this->config['project_id'], [
                    'search' => $report->identifier(),
                    'state'  => 'opened',
                ]);

                $labels = $this->labels ?? '';

                if (empty($issues)) {
                    $this->client->issues()->create($this->config['project_id'],
                        [
                            'title' => $report->title(),
                            'description' => $report->description(),
                            'labels'      => is_array($labels) ? implode(',', $labels) : $labels,
                        ]
                    );
                }
            } catch (Throwable $exp) {
                if ($this->config['debug']) {
                    throw $exp;
                }
            }
        }
    }

    /**
     * Returns the right reporter class based on the exception given.
     *
     * @param Throwable $exception
     *
     * @return mixed|string
     */
    private function reporter(Throwable $exception)
    {
        // Set default class
        $rc = ExceptionReport::class;

        foreach ($this->reporters as $key => $reporter) {
            if (is_a($exception, $key) && is_a(Report::class, $reporter)) {
                $rc = $reporter;
            }
        }

        return $rc;
    }

    /**
     * Returns the current Request.
     *
     * @return Request
     */
    private function request()
    {
        return app(Request::class);
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
            $this->config['ignore-exceptions'],
            function ($class) use ($exception) {
                return is_a($exception, $class);
            }
        );

        return count($ignored) > 0;
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
     * Hides any sensitive information in a request object.
     *
     * @param Request $request
     *
     * @return Request
     */
    private function redactRequest(Request $request)
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
    private function redactArray($array)
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
    private function redact($key, $value)
    {
        if (in_array($key, $this->config['redacted-fields'], true)) {
            return '[hidden]';
        } else {
            return $value;
        }
    }
}
