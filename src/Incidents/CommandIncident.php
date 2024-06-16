<?php

namespace Wyox\GitlabReport\Incidents;

use Wyox\GitlabReport\Components\CommandSummaryComponent;
use Wyox\GitlabReport\Components\SQLComponent;
use Wyox\GitlabReport\Components\TraceComponent;

class CommandIncident extends Incident
{
    public array $argv = [];

    public function __construct($exception, $argv = [])
    {
        parent::__construct($exception);
        $this->argv = $argv;
    }

    public function signature(): string
    {
        return "command_" . implode("_", $this->argv) . "_" . get_class($this->exception) . "_" . $this->exception->getMessage() . "_" . $this->exception->getMessage() . $this->exception->getFile() . ":" . $this->exception->getLine();
    }

    public function components(): array
    {
        return [
            new CommandSummaryComponent($this),
            new SQLComponent($this),
            new TraceComponent($this)
        ];
    }
}
