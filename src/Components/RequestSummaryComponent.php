<?php

namespace Wyox\GitlabReport\Components;

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
        if (!empty($request)) {
            $authenticatedUser = $request->user();
            $isAuthenticated = !empty($authenticatedUser) ? 'Yes' : 'No';
        }

        // Remove any new lines as that will break the Markdown table
        $message = trim(preg_replace('/\s+/', ' ', !empty($exception->getMessage())
            ? $exception->getMessage()
            : get_class($exception)));


        return <<<EOF
#### Exception summary
|  Type     |  Value   |
| :-------- | :------- |
| Type of   | {$exceptionName} |
| Message   | {$message} |
| File      | {$exception->getFile()}:{$exception->getLine()} |
| Method    | {$request->getMethod()} |
| Schema    | {$request->getScheme()} |
| Path      | {$request->getPathInfo()} |
| URL       | {$request->getScheme()}://{$request->getHttpHost()}{$request->getRequestUri()} |
| Authenticated | {$isAuthenticated} | Identifier: `{$this->incident->hash()}`


EOF;
    }
}
