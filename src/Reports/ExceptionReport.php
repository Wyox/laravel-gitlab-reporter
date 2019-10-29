<?php
/**
 *
 * @author Ivo de Bruijn <ivo@idobits.nl>
 */

namespace Wyox\GitlabReport\Reports;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

class ExceptionReport extends Report
{
    protected $request;
    protected $exception;



    public function __construct(Exception $exception, Request $request)
    {
        $this->exception = $exception;
        $this->request  = $request;
    }

    /**
     * Generates a description for the report
     * @return string
     */
    public function description()
    {
        // Return html string in Gitlab flavoured markdown
        // Due to the render identifier being so close to renderSummary the current markdown version of Gitlab (11.0.2) renders the identifier invisible.
        // Highly likely to change if the markdown render engine changes in future versions. For now it's a simple hack to get around EE requirements for custom variables
        return $this->renderSummary() .
            $this->identifier() .
            $this->renderUrl() .
            $this->renderForm() .
            $this->renderSession() .
            $this->renderException();
    }

    /**
     * Returns a human readable severity code instead of a number. (e.g. E_NOTICE)
     * @return string
     */
    public function message()
    {
        return !empty($this->exception->getMessage())
            ? $this->exception->getMessage()
            : get_class($this->exception);
    }

    /**
     * Renders FORM data
     * @return string
     */
    protected function renderForm()
    {
        $str = "#### Post Params\n\n```php\n";
        $str .= $this->renderValue((new Collection($this->request->request->all())));
        $str .= "```" . $this->newline();
        return $str;
    }

    /**
     * Renders URL parameters
     * @return string
     */
    protected function renderUrl()
    {
        $str = "#### Url Params\n\n```php\n";
        $str .= $this->renderValue((new Collection($this->request->query->all())));
        $str .= "```" . $this->newline();
        return $str;
    }

    /**
     * Renders session values
     * @return string
     */
    protected function renderSession()
    {
        $str = "#### Session Params\n\n```php\n";
        $str .= $this->renderValue($this->session());
        $str .= "```" . $this->newline();
        return $str;
    }

    /**
     * Renders a value
     * @param $value
     * @return string
     */
    protected function renderValue($value)
    {
        $cloner = new VarCloner();
        $dumper = new CliDumper();
        $output = '';

        $dumper->dump(
            $cloner->cloneVar($value),
            function ($line, $depth) use (&$output) {
                // A negative depth means "end of dump"
                if ($depth >= 0) {
                    // Adds a two spaces indentation to the line
                    $output .= str_repeat('  ', $depth) . $line . "\n";
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
    protected function renderSummary()
    {
        $exception = get_class($this->exception);

        return <<<EOF
#### Error summary
|  Type     |  Value   |
| :-------- | :------- |
| Type of   | {$exception}|
| Method    | {$this->request->getMethod()} |
| Schema    | {$this->request->getScheme()} |
| Path      | {$this->request->getPathInfo()} |
| URL       | {$this->request->getScheme()}://{$this->request->getHttpHost()}{$this->request->getRequestUri()} |
| Message   | {$this->message()} |
| File      | {$this->exception->getFile()}:{$this->exception->getLine()} |
EOF;

    }

    /**
     * Renders exception message in Markdown format
     * @return string
     */
    protected function renderException()
    {
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
    public function identifier()
    {
        return <<<EOF
            Identifier: `{$this->signature()}`

EOF;

    }

    /**
     * Helper function, real newline is double newline in Markdown
     * @return string
     */
    protected function newline()
    {
        return "\n\r\n\r";
    }

    private function session()
    {
        return $this->request->hasSession() ? $this->request->session()->all() : collect();
    }


}
