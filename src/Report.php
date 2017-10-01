<?php
/**
 *
 * @author Ivo de Bruijn <ik@ivodebruijn.nl>
 */

namespace Wyox\GitlabReport;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

class Report
{
    private $request;
    private $get;
    private $form;
    private $session;
    private $exception;
    private $httpMethod;
    private $host;
    private $schema;


    public function __construct(Exception $exception, Request $request)
    {
        $this->exception = $exception;
        $this->request = $request;

        $this->get = collect($request->query());
        // Filter all parameters from get, usually not needed or available in the form
        $this->form = collect($request->all())->diffKeys($this->get);

        $this->session = $request->hasSession() ? $request->session()->all() : collect();
        $this->path = $request->getPathInfo();
        $this->httpMethod = $request->getMethod();
        $this->host = $request->getHttpHost();
        $this->url = $request->getRequestUri();
        $this->schema = $request->getScheme();
    }

    /**
     * Same as description
     * @return string
     */
    public function render(){
        return $this->description();
    }

    /**
     * Generates a description for the report
     * @return string
     */
    public function description(){
        // Return html string in Gitlab flavoured markdown
        return $this->renderSummary(). $this->renderUrl() . $this->renderForm(). $this->renderSession() . $this->renderException();
    }

    /**
     * Generates a GitLab issue title
     * @return string
     */
    public function title(){
        return "BUG: " . $this->message() . " - "  . $this->signature();
    }

    /**
     * This returns a unique signature based on the exception, the query and input parameters
     * @return string
     */

    public function signature(){
        // Signature should be unique to the error (ignore session for now)
        $key = $this->message() . $this->exception->getFile() . $this->exception->getTraceAsString() . $this->exception->getCode();
        // This might fail if it has complex objects
        $key .= $this->form->toJson();
        $key .= $this->get->toJson();


        return hash('md5',$key);
    }

    /**
     * Returns a human readable severity code instead of a number. (e.g. E_NOTICE)
     * @return string
     */
    private function message(){
        $str = $this->exception->getMessage();

        if(empty($str)){
            $str = get_class($this->exception);
        }

        return $str;
    }

    /**
     * Renders FORM data
     * @return string
     */
    private function renderForm(){
        $str = "#### Post Params\n\n```php\n";
        $str .= $this->renderValue($this->form);
        $str .= "```" . $this->newline();
        return $str;
    }

    /**
     * Renders URL parameters
     * @return string
     */
    private function renderUrl(){
        $str = "#### Url Params\n\n```php\n";
        $str .= $this->renderValue($this->get);
        $str .= "```" . $this->newline();
        return $str;
    }

    /**
     * Renders session values
     * @return string
     */
    private function renderSession(){
        $session = $this->session;
        $str = "#### Session Params\n\n```php\n";
        $str .= $this->renderValue($session);
        $str .= "```" . $this->newline();
        return $str;
    }

    /**
     * Renders a value
     * @param $value
     * @return string
     */
    private function renderValue($value){
        $cloner = new VarCloner();
        $dumper = new CliDumper();
        $output = '';

        $dumper->dump(
            $cloner->cloneVar($value),
            function ($line, $depth) use (&$output) {
                // A negative depth means "end of dump"
                if ($depth >= 0) {
                    // Adds a two spaces indentation to the line
                    $output .= str_repeat('  ', $depth).$line."\n";
                }
            }
        );

        return $output;
    }


    private function renderSummary(){
        return <<<EOF
#### Error summary
|  item    |  value   |
| :------- | :------- |
| Method   | {$this->httpMethod} |
| Schema   | {$this->schema} |
| Path     | {$this->path} |
| URL      | {$this->schema}://{$this->host}{$this->url} |
| Message  | {$this->message()} |
| File     | {$this->exception->getFile()}:{$this->exception->getLine()} |


EOF;

    }

    /**
     * Renders exception message in Markdown format
     * @return string
     */
    private function renderException(){
        return <<<EOF
**Trace** 
```php
{$this->exception->getTraceAsString()}
```


EOF;

    }

    /**
     * Helper function, real newline is double newline in Markdown
     * @return string
     */
    private function newline(){
        return "\n\r\n\r";
    }


}