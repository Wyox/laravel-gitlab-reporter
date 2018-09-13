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


/**
 * Class GitlabReportService
 * @package Wyox\GitlabReport
 */
class GitlabReportService
{
    /**
     * @var Gitlab\Client
     */
    private $client;
    /**
     * @var string Project ID given in GitLab
     */
    private $project_id;
    /**
     * @var string Contains all the labels applied to an issue
     */
    private $labels;
    /**
     * @var array Contains all classes that will be ignored
     */
    private $ignoreExceptions;
    /**
     * @var array contains all fields as string that will be redacted in a report
     */
    private $redactedFields;

    /**
     * @var array
     */
    private $reporters = [
        QueryException::class => DatabaseReport::class
    ];

    /**
     * GitlabReportService constructor.
     * @param string $url
     * @param string $token
     * @param string $project_id
     */

    public function __construct($url, $token, $project_id, $labels, $ignoreExceptions, $redactedFields)
    {
        $this->client = Client::create($url)->authenticate($token,Client::AUTH_URL_TOKEN);
        $this->project_id = $project_id;
        $this->labels = $labels;
        $this->ignoreExceptions = $ignoreExceptions;
        $this->redactedFields = $redactedFields ? $redactedFields : [] ;
        return $this;
    }


    /**
     * GitlabReport function to report exceptions. This will generate a GitlabReport and send it to GitLab as issue under the project
     * @param Exception $exception
     */
    public function report(Exception $exception){
        if(!$this->isIgnored($exception)){
            try {

                // Get current request
                $request = $this->redactRequest($this->request());

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
                // throw $exp;
            }
        }


        return;
    }

    /**
     * Returns the right reporter class based on the exception given
     * @param Exception $exception
     * @return mixed|string
     */
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


    /**
     * Returns if the exception is ignored based on the configuration
     * @param Exception $exception
     * @return bool
     */
    private function isIgnored(Exception $exception){
        $ignored = false;


        foreach($this->ignoreExceptions as $class){
            if(is_a($exception, $class)){
               $ignored = true;
               break;
            }
        }

        return $ignored;
    }


    /**
     * Redacts a request object. (This ensures reports won't know anything about this either)
     * @param Request $request
     * @return Request
     */
    private function redactRequest(Request $request){

        $request->query->replace($this->redactArray($request->query->all()));
        $request->request->replace($this->redactArray($request->request->all()));

        if($request->hasSession()){
            $request->session()->replace($this->redactArray($request->session()->all()));
        }

        return $request;
    }

    /**
     * Redacts an array (recursive loop)
     * @param $array
     * @return mixed
     */
    private function redactArray($array){
        foreach($array as $key => $value){
            if(is_array($array[$key])){
                $array[$key] = $this->redactArray($array[$key]);
            }

            if(is_string($array[$key]) || is_bool($array[$key]) || is_numeric($array[$key]) || is_null($array[$key])){
                $array[$key] = $this->redact($key,$value);
            }
        }

        return $array;
    }

    /**
     * Simple redact function.
     * @param $key
     * @param $value
     * @return string
     */
    private function redact($key, $value){
        if(in_array($key, $this->redactedFields, true)){
            return "[redacted]";
        }else{
            return $value;
        }
    }



}