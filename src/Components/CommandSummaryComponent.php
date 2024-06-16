<?php

namespace Wyox\GitlabReport\Components;

use InvalidArgumentException;
use Wyox\GitlabReport\Incidents\CommandIncident;

class CommandSummaryComponent extends MarkdownComponent
{
    public function render(): string
    {
        // Fast fail
        if (!$this->incident instanceof CommandIncident) {
            throw new InvalidArgumentException('CommandSummaryComponent must have a CommandIncident object as $incident property otherwise it cannot render an error');
        }

        $exception = $this->incident->exception;
        $exceptionName = get_class($exception);
        $args = $this->incident->argv;
        $commandName = $args[0];
        $arguments = implode(" ", array_slice($args, 1));
        $message = trim(preg_replace('/\s+/', ' ', !empty($exception->getMessage())
            ? $exception->getMessage()
            : get_class($exception)));

        // Identifier is after the table as this makes it invisible to the user and allows us to search for the issue.

        return <<<EOF
#### Exception summary
|  Type     |  Value   |
| :-------- | :------- |
| Type of   | {$exceptionName} |
| Message   | { $message } |
| File      | {$exception->getFile()}:{$exception->getLine()} |
| Command   | `{$commandName}` |
| Arguments  | `{$arguments}` | Identifier: `{$this->incident->hash()}`

EOF;
    }
}
