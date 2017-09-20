<?php

namespace Wyox\GitlabReport;

use \Illuminate\Support\Facades\Facade;


class GitlabReportFacade extends Facade {
    protected static function getFacadeAccessor() {
        return 'gitlab.report';
    }
}