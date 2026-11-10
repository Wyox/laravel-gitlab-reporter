<?php

namespace Wyox\GitlabReport\Incidents;

use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Throwable;
use Wyox\GitlabReport\Components\MarkdownComponent;

abstract class Incident
{
    public Throwable $exception;

    public function __construct($exception)
    {
        $this->exception = $exception;
    }

    /**
     * The message goes in a code span: GitLab turns @mentions, #references and URLs in a title into notifications,
     * cross-reference notes and links, but leaves code spans alone. Titles can't escape backticks with a longer
     * fence, so backticks in the message become quotes.
     */
    public function title(): string
    {
        $message = trim(str_replace('`', "'", preg_replace('/\s+/u', ' ', self::clean($this->message()))));

        return 'BUG: `' . mb_substr($message !== '' ? $message : get_class($this->exception), 0, 246) . '`';
    }

    /**
     * Makes text safe to send to GitLab: invalid UTF-8 would make the request fail (and the report get lost), NUL
     * bytes are rejected by GitLab and bidirectional control characters can make text read differently than it is.
     */
    public static function clean(string $value): string
    {
        return preg_replace('/[\x{0}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', mb_scrub($value, 'UTF-8'));
    }

    /**
     * The message as it may be reported: Laravel puts query bindings into a QueryException message, so those are
     * swapped back for `?` placeholders unless `sql-bindings` is on, then the optional `message-redactor` runs.
     * Falls back to the class name for an empty message.
     */
    public function message(?Throwable $exception = null): string
    {
        $exception ??= $this->exception;
        $message = $exception->getMessage();

        if ($exception instanceof QueryException && !config('gitlab-report.sql-bindings', false)) {
            $withBindings = Str::replaceArray('?', $exception->getBindings(), $exception->getSql());
            $message = str_replace($withBindings, $exception->getSql(), $message);
        }

        $redactor = config('gitlab-report.message-redactor');

        if (!empty($redactor)) {
            $redactor = is_string($redactor) && class_exists($redactor) ? app($redactor) : $redactor;
            $message = (string) $redactor($message);
        }

        return $message !== '' ? $message : get_class($exception);
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
        $markdown = self::clean(implode("\n\n", array_filter(array_map(function (MarkdownComponent $component) {
            return $component->render();
        }, $components))));

        // Make sure our markdown string doesn't hit GitLab description limits
        return mb_strcut($markdown, 0, 1048575);
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
