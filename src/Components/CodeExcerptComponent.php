<?php

namespace Wyox\GitlabReport\Components;

class CodeExcerptComponent extends MarkdownComponent
{
    /**
     * Number of source lines to show before and after the offending line.
     */
    private const CONTEXT_LINES = 8;

    /**
     * Never read a source file larger than this (bytes) to avoid loading
     * huge generated files into memory.
     */
    private const MAX_FILE_SIZE = 2 * 1024 * 1024;

    public function render(): string
    {
        $exception = $this->incident->exception;
        $file = $exception->getFile();
        $line = $exception->getLine();

        if (empty($file) || !is_file($file) || !is_readable($file)) {
            return '';
        }

        if (filesize($file) > self::MAX_FILE_SIZE) {
            return '';
        }

        $lines = @file($file, FILE_IGNORE_NEW_LINES);

        if ($lines === false || $line < 1) {
            return '';
        }

        $total = count($lines);
        $start = max(1, $line - self::CONTEXT_LINES);
        $end = min($total, $line + self::CONTEXT_LINES);
        $padding = strlen((string) $end);

        $snippet = '';
        for ($number = $start; $number <= $end; $number++) {
            $marker = $number === $line ? '>' : ' ';
            $label = str_pad((string) $number, $padding, ' ', STR_PAD_LEFT);
            $snippet .= "{$marker} {$label} | {$lines[$number - 1]}\n";
        }

        $relative = $this->relativePath($file);

        return <<<EOF
#### Code

`{$relative}:{$line}`

```php
{$snippet}```


EOF;
    }

    /**
     * Strip the application base path so the rendered path stays readable.
     */
    private function relativePath(string $file): string
    {
        if (function_exists('base_path')) {
            $base = base_path();

            if (!empty($base) && str_starts_with($file, $base)) {
                return ltrim(substr($file, strlen($base)), DIRECTORY_SEPARATOR);
            }
        }

        return $file;
    }
}
