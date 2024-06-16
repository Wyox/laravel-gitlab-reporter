<?php

namespace Wyox\GitlabReport\Components;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Wyox\GitlabReport\Incidents\RequestIncident;

class RequestComponent extends MarkdownComponent
{
    public function render(): string
    {
        return $this->get() . $this->post() . $this->session() . $this->user();
    }

    private function get(): string
    {
        // Fast fail
        if (!$this->incident instanceof RequestIncident) {
            throw new InvalidArgumentException('RequestSummaryComponent must have a RequestIncident object as $incident property otherwise it cannot render an error');
        }

        $request = $this->incident->request;
        $query = $request->query->all();

        $str = "#### Query params\n\n";
        if (empty($post)) {
            $str .= '*No Query parameters*';
            $str .= "\n\r\n\r";
            return $str;
        }


        $str .= '<details>';
        $str .= "<summary>Click me to collapse/fold.</summary>\n\n";
        $str .= "```php\n";
        $str .= $this->renderValue($query);
        $str .= "```\n\n";
        $str .= '</details>';
        $str .= "\n\r\n\r";

        return $str;
    }

    private function post(): string
    {
        // Fast fail
        if (!$this->incident instanceof RequestIncident) {
            throw new InvalidArgumentException('RequestSummaryComponent must have a RequestIncident object as $incident property otherwise it cannot render an error');
        }

        $request = $this->incident->request;
        $post = $request->all();
        $str = "#### POST data\n\n";

        if (empty($post)) {
            $str .= '*No POST data*';
            $str .= "\n\r\n\r";
            return $str;
        }

        $str .= '<details>';
        $str .= "<summary>Click me to collapse/fold.</summary>\n\n";
        $str .= "```php\n";
        $str .= $this->renderValue($post);
        $str .= "```\n\n";
        $str .= '</details>';
        $str .= "\n\r\n\r";

        return $str;
    }

    private function session(): string
    {
        // Fast fail
        if (!$this->incident instanceof RequestIncident) {
            throw new InvalidArgumentException('RequestSummaryComponent must have a RequestIncident object as $incident property otherwise it cannot render an error');
        }

        $request = $this->incident->request;
        $session = [];

        if ($request->hasSession()) {
            $session = $request->getSession()->all();
        }

        $str = "#### Session data\n\n";
        $str .= '<details>';
        $str .= "<summary>Click me to collapse/fold.</summary>\n\n";
        $str .= "```php\n";
        $str .= $this->renderValue($session);
        $str .= "```\n\n";
        $str .= '</details>';
        $str .= "\n\r\n\r";

        return $str;
    }

    private function user(): string
    {
        // Fast fail
        if (!$this->incident instanceof RequestIncident) {
            throw new InvalidArgumentException('RequestSummaryComponent must have a RequestIncident object as $incident property otherwise it cannot render an error');
        }

        $request = $this->incident->request;

        $user = $request->user();
        $str = "#### User \n\n";

        // In-case we are working with a user that hasn't authenticated with the system
        if (empty($user)) {
            $str .= '*Anonymous user*';
            $str .= "\n\r\n\r";
            return $str;
        }

        $userData = [];

        if ($user instanceof Model) {
            $userData = $user->getAttributes();
        }

        $str .= '<details>';
        $str .= "<summary>Click me to collapse/fold.</summary>\n\n";
        $str .= "```php\n";
        $str .= $this->renderValue($userData);
        $str .= "```\n\n";
        $str .= '</details>';
        $str .= "\n\r\n\r";

        return $str;
    }
}
