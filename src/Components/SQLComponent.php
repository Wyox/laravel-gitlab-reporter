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

        $sql = config('gitlab-report.sql-bindings', false)
            ? Str::replaceArray('?', $exception->getBindings(), $exception->getSql())
            : $exception->getSql();

        return "#### SQL\n\n" . $this->fence($sql, 'sql') . "\n\n";
    }
}
