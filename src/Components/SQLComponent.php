<?php

namespace Wyox\GitlabReport\Components;

use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class SQLComponent extends MarkdownComponent
{
    public function render(): string
    {
        $exception = $this->incident->exception;

        if (!$exception instanceof QueryException) {
            return "";
        }

        $str = "#### SQL\n\n```sql\n";
        $str .= Str::replaceArray(
            '?',
            $exception->getBindings(),
            $exception->getSql()
        ) . "\n\r\n\r";
        $str .= '```' . "\n\r\n\r";

        return $str;
    }
}
