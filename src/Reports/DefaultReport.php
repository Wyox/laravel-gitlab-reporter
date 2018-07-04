<?php
/**
 *
 * @author Ivo de Bruijn <ivo@idobits.nl>
 */

namespace Wyox\GitlabReport\Reports;

use Exception;
use Illuminate\Http\Request;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

class DefaultReport
{
    protected $request;
    protected $get;
    protected $form;
    protected $session;
    protected $exception;
    protected $httpMethod;
    protected $host;
    protected $schema;


    public function __construct(Exception $exception, Request $request)
    {
        $this->exception    = $exception;
        $this->request      = $request;



        // Get all input from the user
        $this->get      = collect($request->query->all());
        $this->form     = collect($request->request->all());

        // Request variables
        $this->path     = $request->getPathInfo();
        $this->httpMethod = $request->getMethod();
        $this->host     = $request->getHttpHost();
        $this->url      = $request->getRequestUri();
        $this->schema   = $request->getScheme();

        // Session information
        $this->session  = $request->hasSession() ? $request->session()->all() : collect();
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
        // Due to the render identifier being so close to renderSummary the current markdown version of Gitlab (11.0.2) renders the identifier invisible.
        // Highly likely to change if the markdown render engine changes in future versions. For now it's a simple hack to get around EE requirements for custom variables
        return $this->renderSummary() . $this->renderIdentifier() . $this->renderUrl() . $this->renderForm() . $this->renderSession() . $this->renderException();
    }

    /**
     * Generates a GitLab issue title
     * @return string
     */
    public function title(){
        return "BUG: " . $this->message();
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
    protected function message(){
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
    protected function renderForm(){
        $str = "#### Post Params\n\n```php\n";
        $str .= $this->renderValue($this->form);
        $str .= "```" . $this->newline();
        return $str;
    }

    /**
     * Renders URL parameters
     * @return string
     */
    protected function renderUrl(){
        $str = "#### Url Params\n\n```php\n";
        $str .= $this->renderValue($this->get);
        $str .= "```" . $this->newline();
        return $str;
    }

    /**
     * Renders session values
     * @return string
     */
    protected function renderSession(){
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
    protected function renderValue($value){
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

    /**
     * Renders the top summary of an issue with simple information
     * @param $value
     * @return string
     */
    protected function renderSummary(){
        $exception = get_class($this->exception);

        return <<<EOF
#### Error summary
|  Type     |  Value   |
| :-------- | :------- |
| Type of   | {$exception}|
| Method    | {$this->httpMethod} |
| Schema    | {$this->schema} |
| Path      | {$this->path} |
| URL       | {$this->schema}://{$this->host}{$this->url} |
| Message   | {$this->message()} |
| File      | {$this->exception->getFile()}:{$this->exception->getLine()} |
EOF;

    }

    /**
     * Renders exception message in Markdown format
     * @return string
     */
    protected function renderException(){
        return <<<EOF
**Trace** 
```php
{$this->exception->getTraceAsString()}
```


EOF;

    }
    /**
     * Renders the identifier which will be used to find issues in a project
     * @return string
     */
    protected function renderIdentifier(){
        $signature = $this->signature();

        return <<<EOF
            Identifier: `{$signature}`

EOF;

    }

    /**
     * Helper function, real newline is double newline in Markdown
     * @return string
     */
    protected function newline(){
        return "\n\r\n\r";
    }


}