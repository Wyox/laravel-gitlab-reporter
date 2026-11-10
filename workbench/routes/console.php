<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('boom {--force}', fn () => throw new RuntimeException('Boom from the console'));
