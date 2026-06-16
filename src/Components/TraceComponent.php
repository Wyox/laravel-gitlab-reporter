<?php

namespace Wyox\GitlabReport\Components;

use Throwable;

class TraceComponent extends MarkdownComponent
{
    /**
     * Maximum depth of the previous-exception chain we walk to avoid
     * pathological or self-referencing chains.
     */
    private const MAX_DEPTH = 10;

    public function render(): string
    {
        $exception = $this->incident->exception;

        $str = "**Trace**\n```php\n{$exception->getTraceAsString()}\n```\n\r\n\r";

        $previous = $exception->getPrevious();
        $depth = 0;

        while ($previous instanceof Throwable && $depth < self::MAX_DEPTH) {
            $type = get_class($previous);
            $message = trim(preg_replace('/\s+/', ' ', $previous->getMessage()));
            $location = $previous->getFile() . ':' . $previous->getLine();

            $str .= "**Caused by:** `{$type}` — {$message}\n\r";
            $str .= "_{$location}_\n\n";
            $str .= "```php\n{$previous->getTraceAsString()}\n```\n\r\n\r";

            $previous = $previous->getPrevious();
            $depth++;
        }

        return $str;
    }
}
