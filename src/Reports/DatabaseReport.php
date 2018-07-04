<?php
/**
 * Created by PhpStorm.
 * User: ivodebruijn
 * Date: 04/07/2018
 * Time: 23:32
 */

namespace Wyox\GitlabReport\Reports;

use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DatabaseReport extends DefaultReport
{

    public function title()
    {
        return "DATABASE: " . $this->message();
    }

    /**
     * Generates a description for the report
     * @return string
     */
    public function description(){
        // Return html string in Gitlab flavoured markdown
        return $this->renderSummary() . $this->renderIdentifier() . $this->renderSQL() . $this->renderUrl() . $this->renderForm() . $this->renderSession() . $this->renderException();
    }


    /**
     * Renders Queries
     * @return string
     */
    protected function renderSQL(){
        $exception = $this->exception;
        $str = "#### SQL\n\n```sql\n";
        $str .= Str::replaceArray('?', $this->exception->getBindings(), $this->exception->getSql()) . $this->newline();
        $str .= "```" . $this->newline();

        return $str;
    }

}