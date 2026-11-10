<?php

namespace Wyox\GitlabReport\Components;

use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Wyox\GitlabReport\Incidents\Incident;

abstract class MarkdownComponent
{
    /**
     * Longest value rendered in a table cell, and longest string in a dumped value. Keeps a single huge value from
     * filling the description, and keeps the 1 MB description cut away from code spans.
     */
    private const MAX_LENGTH = 10000;

    protected Incident $incident;

    public function __construct(Incident $incident)
    {
        $this->incident = $incident;
    }

    abstract public function render(): string;

    /**
     * Renders a value.
     *
     * @param $value
     *
     * @return string
     */
    protected function renderValue($value)
    {
        $cloner = new VarCloner();
        $cloner->setMaxString(self::MAX_LENGTH);
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
     * A table cell value as inline code, so client-controlled text (user agent, message, URL) can't render links,
     * images or @mentions, or break the table with `|` or a newline.
     */
    protected function cell(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', Incident::clean($value)));

        if ($value === '') {
            return '';
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            $value = mb_substr($value, 0, self::MAX_LENGTH) . '...';
        }

        $ticks = str_repeat('`', $this->longestBacktickRun($value) + 1);
        $padding = str_starts_with($value, '`') || str_ends_with($value, '`') ? ' ' : '';

        return $ticks . $padding . str_replace('|', '\|', $value) . $padding . $ticks;
    }

    /**
     * A fenced code block whose fence is longer than any backtick run in the content, so the content can't close it
     * and continue as live markdown (or GitLab quick actions).
     */
    protected function fence(string $content, string $language = ''): string
    {
        // GitLab drops carriage returns before it looks for quick actions, which can join backtick runs that a CR
        // kept apart; normalising them first means the fence is measured on the text GitLab sees.
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $ticks = str_repeat('`', max(3, $this->longestBacktickRun($content) + 1));

        return "{$ticks}{$language}\n" . rtrim($content, "\n") . "\n{$ticks}\n";
    }

    private function longestBacktickRun(string $value): int
    {
        preg_match_all('/`+/', $value, $matches);

        return max([0, ...array_map('strlen', $matches[0])]);
    }
}
