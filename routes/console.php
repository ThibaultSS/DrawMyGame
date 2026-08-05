<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Anyone may upload and play without an account, so most uploads are never
// saved. Without this they would stay on disk forever.
Schedule::command('levels:prune')->daily();
