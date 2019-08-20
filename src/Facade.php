<?php

namespace Wyox\GitlabReport;

class Facade extends \Illuminate\Support\Facades\Facade
{
    protected static function getFacadeAccessor()
    {
        return GitlabReportService::class;
    }
}
