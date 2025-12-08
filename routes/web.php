<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/upload-file-list', [App\Http\Controllers\BaseController::class, 'uploadListFiles']);
