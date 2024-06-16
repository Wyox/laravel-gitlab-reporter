<?php

namespace Wyox\GitlabReport\Incidents;

use Illuminate\Http\Request;
use Throwable;
use Wyox\GitlabReport\Components\RequestComponent;
use Wyox\GitlabReport\Components\RequestSummaryComponent;
use Wyox\GitlabReport\Components\SQLComponent;
use Wyox\GitlabReport\Components\TraceComponent;

class RequestIncident extends Incident
{
    public Request $request;

    public function __construct(Throwable $exception, Request $request)
    {
        parent::__construct($exception);
        $this->request = $request;
    }

    public function signature(): string
    {
        return "request_" . $this->request->getHttpHost() . "_" . $this->request->path() . "_" . get_class($this->exception) . "_" . $this->exception->getMessage() . $this->exception->getFile() . ":" . $this->exception->getLine();
    }

    public function components(): array
    {
        return [
            new RequestSummaryComponent($this),
            new SQLComponent($this),
            new RequestComponent($this),
            new TraceComponent($this)
        ];
    }
}
