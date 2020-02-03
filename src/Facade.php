<?php

namespace Wyox\GitlabReport;

class Facade extends \Illuminate\Support\Facades\Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return GitlabReportService::class;
    }
}
