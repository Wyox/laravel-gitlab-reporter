<?php
/**
 * Created by PhpStorm.
 * User: ivodebruijn
 * Date: 20/09/2017
 * Time: 20:42
 */

namespace Wyox\GitlabReport;

// Use default Request facade
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

use Gitlab\Client;
use Gitlab\Model\Project;


use Exception;
use Wyox\GitlabReport\Reports\DatabaseReport;
use Wyox\GitlabReport\Reports\DefaultReport;


class GitlabReportService
{
    private $client;
    private $project_id;
    private $labels;

    private $reporters = [
        QueryException::class => DatabaseReport::class
    ];

    /**
     * GitlabReportService constructor.
     * @param string $url
     * @param string $token
     * @param string $project_id
     */

    public function __construct($url, $token, $project_id, $labels)
    {
        $this->client = Client::create($url)->authenticate($token,Client::AUTH_URL_TOKEN);
        $this->project_id = $project_id;
        $this->labels = $labels;
        return $this;
    }


    /**
     * GitlabReport function to report exceptions. This will generate a GitlabReport and send it to GitLab as issue under the project
     * @param Exception $exception
     */
    public function report(Exception $exception){

        try {

            // Get current request
            $request = $this->request();

            $reporter = $this->reporter($exception);

            $report = new $reporter($exception, $request);

            $project = new Project($this->project_id, $this->client);

            // Check if an issue exists with the same title and is currently open.
            $issues = $project->issues(['search' => "Identifier: `{$report->signature()}`", 'state' => 'opened']);


            if (empty($issues)) {
                $issue = $project->createIssue($report->title(), [
                    'description' => $report->description(),
                    'labels' => $this->labels
                ]);
            }
        } catch (Exception $exp){
            // Only for testing
            throw $exp;
        }


        return;
    }

    private function reporter(Exception $exception){
        // Get right reporter
        $rc = DefaultReport::class;
        
        foreach($this->reporters as $key => $reporter){
            if(is_a($exception, $key)){
                $rc = $reporter;
            }
        }


        return $rc;
    }

    /**
     * Returns the current Request
     * @return \Illuminate\Http\Request
     */
    private function request() {
        return app(Request::class);
    }

}