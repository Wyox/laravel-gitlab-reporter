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
        if ($host !== false) {
            $rows['Host'] = $host;
        }

        $rows['Occurred at'] = date('c');

        if ($this->incident instanceof RequestIncident) {
            $request = $this->incident->request;

            if (!empty($request)) {
                $ip = $request->ip();
                if (!empty($ip)) {
                    $rows['Client IP'] = $ip;
                }

                $agent = $request->userAgent();
                if (!empty($agent)) {
                    $rows['User agent'] = trim(preg_replace('/\s+/', ' ', $agent));
                }
            }
        }

        $table = "#### Context\n\n|  Key      |  Value   |\n| :-------- | :------- |\n";
        foreach ($rows as $key => $value) {
            $table .= "| {$key} | {$value} |\n";
        }

        $table .= "\n\r\n\r";

        return $table;
    }
}
