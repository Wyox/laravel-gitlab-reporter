<?php
/**
 * Created by PhpStorm.
 * User: ivodebruijn
 * Date: 20/09/2017
 * Time: 20:42
 */

namespace Wyox\GitlabReport;

// Use default Request facade
use Illuminate\Http\Request;

use Gitlab\Client;
use Gitlab\Model\Project;

use Exception;


class GitlabReportService
{
        private $client;
        private $project_id;

    /**
     * GitlabReportService constructor.
     * @param string $url
     * @param string $token
     * @param string $project_id
     */

    public function __construct($url, $token, $project_id)
        {
            $this->client = Client::create($url)->authenticate($token,\Gitlab\Client::AUTH_URL_TOKEN);
            $this->project_id = $project_id;
            return $this;
        }


    /**
     * Report function to report exceptions. This will generate a Report and send it to GitLab as issue under the project
     * @param Exception $exception
     */
    public function report(Exception $exception){
            // Get current request
            $request = $this->request();

            $report = new Report($exception, $request->query(), $request->all(), $request->session() );

            $project = new Project($this->project_id,$this->client);


            // Check if an issue exists with the same title.
            $issues = $project->issues(['search' => $report->title()]);

            if(empty($issues)){
                $issue = $project->createIssue($report->title(), [
                    'description' => $report->description()
                ]);
            }

            return;
        }


    /**
     * Returns the current Request
     * @return \Illuminate\Http\Request
     */
    private function request() {
            return app(Request::class);
        }

}