<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/clear-cache', function () {
    Artisan::call('permission:cache-reset');
    Artisan::call('optimize:clear');
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    return 'Cache cleared';
});

Route::get('/run-migrations', function () {
    // ← حماية بسيطة بمفتاح سري
    // if (request('secret') !== env('MIGRATION_SECRET')) {
    //     abort(403, 'Unauthorized');
    // }

    Artisan::call('migrate', ['--force' => true]);

    return response()->json([
        'success' => true,
        'output'  => Artisan::output(),
    ]);
});
