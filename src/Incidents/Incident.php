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
     * Normalizes a message for deduplication purposes so that the same logical
     * error does not spawn a new issue every time a dynamic value (id, uuid,
     * hash, ...) changes inside the exception message.
     *
     * @param string|null $value
     *
     * @return string
     */
    protected function normalize(?string $value): string
    {
        $value = $value ?? '';

        // UUIDs
        $value = preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', '<uuid>', $value);
        // Long hex sequences (hashes, tokens, object ids)
        $value = preg_replace('/\b[0-9a-f]{16,}\b/i', '<hex>', $value);
        // Any remaining number sequence
        $value = preg_replace('/\d+/', '<n>', $value);

        return $value;
    }

    /**
     * Must return a unique signature to this incident. This is based of the data available within the incident
     * @return string
     */
    abstract public function signature(): string;
}
