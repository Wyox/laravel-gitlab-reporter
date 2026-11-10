<?php

namespace Wyox\GitlabReport\Components;

use Wyox\GitlabReport\Incidents\RequestIncident;

class ContextComponent extends MarkdownComponent
{
    public function render(): string
    {
        $rows = [];

        if (function_exists('app')) {
            $app = app();

            if (method_exists($app, 'environment')) {
                $rows['Environment'] = (string) $app->environment();
            }

            if (method_exists($app, 'version')) {
                $rows['Laravel'] = (string) $app->version();
            }
        }

        $rows['PHP'] = PHP_VERSION;

        $host = gethostname();
        if ($host !== false && config('gitlab-report.context-host', true)) {
            $rows['Host'] = $host;
        }

        $rows['Occurred at'] = date('c');

        if ($this->incident instanceof RequestIncident) {
            $request = $this->incident->request;

            if (!empty($request)) {
                $ip = $request->ip();
                if (!empty($ip) && config('gitlab-report.context-client-ip', true)) {
                    $rows['Client IP'] = $ip;
                }

                $agent = $request->userAgent();
                if (!empty($agent) && config('gitlab-report.context-user-agent', true)) {
                    $rows['User agent'] = $agent;
                }
            }
        }

        $table = "#### Context\n\n|  Key      |  Value   |\n| :-------- | :------- |\n";
        foreach ($rows as $key => $value) {
            $table .= "| {$key} | {$this->cell($value)} |\n";
        }

        $table .= "\n\n";

        return $table;
    }
}
