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

        $str = "**Trace**\n" . $this->fence($this->trace($exception), 'php') . "\n\n";

        $previous = $exception->getPrevious();
        $depth = 0;

        while ($previous instanceof Throwable && $depth < self::MAX_DEPTH) {
            $type = get_class($previous);
            $message = $this->cell($this->incident->message($previous));
            $location = $previous->getFile() . ':' . $previous->getLine();

            $str .= "**Caused by:** `{$type}` — {$message}\n";
            $str .= "_{$location}_\n\n";
            $str .= $this->fence($this->trace($previous), 'php') . "\n\n";

            $previous = $previous->getPrevious();
            $depth++;
        }

        return $str;
    }

    /**
     * Same layout as getTraceAsString(), but without the call arguments: PHP includes those (cut to 15 characters)
     * unless zend.exception_ignore_args is on, which leaks passwords and tokens passed to the failing calls.
     */
    private function trace(Throwable $exception): string
    {
        $lines = [];

        foreach ($exception->getTrace() as $index => $frame) {
            $location = isset($frame['file']) ? "{$frame['file']}({$frame['line']})" : '[internal function]';
            $call = ($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function'];
            $lines[] = "#{$index} {$location}: {$call}()";
        }

        $lines[] = '#' . count($lines) . ' {main}';

        return implode("\n", $lines);
    }
}
