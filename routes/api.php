<?php

use App\Http\Controllers\api\BaseController;
use Illuminate\Support\Facades\Route;

Route::post('/upload-file-list', [BaseController::class, 'uploadListFiles']);
Route::post('/upload-file', [BaseController::class, 'uploadFileInLical']);
