<?php

namespace Wyox\GitlabReport\Components;

use InvalidArgumentException;
use Wyox\GitlabReport\Incidents\RequestIncident;

class RequestComponent extends MarkdownComponent
{
    public function render(): string
    {
        // Fast fail
        if (!$this->incident instanceof RequestIncident) {
            throw new InvalidArgumentException('RequestComponent must have a RequestIncident object as $incident property otherwise it cannot render an error');
        }

        return $this->section('Query params', $this->incident->query, '*No Query parameters*')
            . $this->section('POST data', $this->incident->post, '*No POST data*')
            . $this->section('Session data', $this->incident->session)
            . $this->section('User ', $this->incident->user, '*Anonymous user*');
    }

    private function section(string $title, ?array $data, ?string $placeholder = null): string
    {
        $str = "#### {$title}\n\n";

        if ($placeholder !== null && empty($data)) {
            return $str . $placeholder . "\n\n";
        }

        $str .= '<details>';
        $str .= "<summary>Click me to collapse/fold.</summary>\n\n";
        $str .= $this->fence($this->renderValue($data ?? []), 'php') . "\n";
        $str .= '</details>';
        $str .= "\n\n";

        return $str;
    }
}
