<?php

namespace Wyox\GitlabReport\Components;

use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Wyox\GitlabReport\Incidents\Incident;

abstract class MarkdownComponent
{
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
}
