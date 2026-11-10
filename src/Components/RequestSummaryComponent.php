<?php

namespace Wyox\GitlabReport\Components;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use Wyox\GitlabReport\Incidents\RequestIncident;

class RequestSummaryComponent extends MarkdownComponent
{
    public function render(): string
    {
        // Fast fail
        if (!$this->incident instanceof RequestIncident) {
            throw new InvalidArgumentException('RequestSummaryComponent must have a RequestIncident object as $incident property otherwise it cannot render an error');
        }

        $request = $this->incident->request;
        $exception = $this->incident->exception;
        $exceptionName = get_class($exception);
        $isAuthenticated = $this->incident->user !== null ? 'Yes' : 'No';
        $message = $this->cell($this->incident->message());
        $path = $this->cell($request->getPathInfo());

        // Built from the redacted query copy, never from the raw request URI.
        $query = $this->incident->query;
        $url = $this->cell($request->url() . (empty($query) ? '' : '?' . Arr::query($query)));

        return <<<EOF
#### Exception summary
|  Type     |  Value   |
| :-------- | :------- |
| Type of   | {$exceptionName} |
| Message   | {$message} |
| File      | {$exception->getFile()}:{$exception->getLine()} |
| Method    | {$request->getMethod()} |
| Schema    | {$request->getScheme()} |
| Path      | {$path} |
| URL       | {$url} |
| Authenticated | {$isAuthenticated} | Identifier: `{$this->incident->hash()}`


EOF;
    }
}
