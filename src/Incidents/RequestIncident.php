<?php

namespace Wyox\GitlabReport\Incidents;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Throwable;
use Wyox\GitlabReport\Components\CodeExcerptComponent;
use Wyox\GitlabReport\Components\ContextComponent;
use Wyox\GitlabReport\Components\RequestComponent;
use Wyox\GitlabReport\Components\RequestSummaryComponent;
use Wyox\GitlabReport\Components\SQLComponent;
use Wyox\GitlabReport\Components\TraceComponent;

class RequestIncident extends Incident
{
    public Request $request;

    public array $query;

    public array $post;

    public array $session;

    public ?array $user;

    /**
     * The query, post, session and user arrays are what gets reported. GitlabReportService passes redacted copies;
     * when left out they are read from the request as-is (and only the user's id is kept).
     */
    public function __construct(
        Throwable $exception,
        Request $request,
        ?array $query = null,
        ?array $post = null,
        ?array $session = null,
        ?array $user = null,
    ) {
        parent::__construct($exception);
        $this->request = $request;
        $this->query = $query ?? $request->query->all();
        $this->post = $post ?? $request->all();
        $this->session = $session ?? ($request->hasSession() ? $request->session()->all() : []);
        $this->user = $user ?? self::userAttributes($request, ['id']);
    }

    public static function userAttributes(Request $request, array $fields): ?array
    {
        $user = $request->user();

        if (empty($user)) {
            return null;
        }

        return $user instanceof Model ? Arr::only($user->getAttributes(), $fields) : [];
    }

    /**
     * Signs on the route template (e.g. `users/{user}`) rather than the concrete path, and leaves out the Host
     * header: both are client controlled and would otherwise create a new issue per distinct value.
     */
    public function signature(): string
    {
        $route = $this->request->route();
        $location = $route instanceof Route ? $route->uri() : $this->normalize($this->request->path());

        return "request_" . $location . "_" . get_class($this->exception) . "_" . $this->normalize($this->message()) . $this->exception->getFile() . ":" . $this->exception->getLine();
    }

    public function components(): array
    {
        return [
            new RequestSummaryComponent($this),
            new ContextComponent($this),
            new CodeExcerptComponent($this),
            new SQLComponent($this),
            new RequestComponent($this),
            new TraceComponent($this)
        ];
    }
}
