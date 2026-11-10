<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => <<<'HTML'
    <h1>GitLab reporter workbench</h1>
    <ul>
        <li><a href="/boom?page=2&password=hunter2">Request exception (with query params)</a></li>
        <li><a href="/query">Query exception (SQL section)</a></li>
        <li><a href="/session">Exception with session data</a></li>
    </ul>
    <p>Console: <code>docker compose exec app vendor/bin/testbench boom --force</code></p>
    <p>Issues land in <code>docker/gitlab-mock/issues/</code>.</p>
    HTML);

Route::get('/boom', fn () => throw new RuntimeException('Boom from the workbench'));

Route::get('/query', fn () => DB::select('select * from missing_table where id = ?', [5]));

Route::get('/session', function () {
    session(['cart' => ['items' => 3], 'password' => 'hunter2']);
    throw new RuntimeException('Boom with session');
});
