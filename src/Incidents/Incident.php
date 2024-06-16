<?php

namespace Wyox\GitlabReport\Incidents;

use Throwable;
use Wyox\GitlabReport\Components\MarkdownComponent;

abstract class Incident
{
    public Throwable $exception;

    public function __construct($exception)
    {
        $this->exception = $exception;
    }

    public function title(): string
    {
        $message = !empty($this->exception->getMessage())
            ? $this->exception->getMessage()
            : get_class($this->exception);

        return substr("BUG: {$message}", 0, 254);
    }

    /**
     * Generate mark down based on all the
     * @return string
     */
    public function markdown(): string
    {
        // Get all components
        $components = $this->components() ?? [];

        // Since everything is rendered as Markdown, just append everything together with 2 new lines to spread them out a bit
        $markdown = implode("\n\r\n\r", array_filter(array_map(function (MarkdownComponent $component) {
            return $component->render();
        }, $components)));

        // Make sure our markdown string doesn't hit GitLab description limits
        return substr($markdown, 0, 1048575);
    }

    /**
     * @return MarkdownComponent[]
     */
    abstract public function components(): array;

    public function hash(): string
    {
        return hash("md5", $this->signature());
    }

    /**
     * Must return a unique signature to this incident. This is based of the data available within the incident
     * @return string
     */
    abstract public function signature(): string;
}
