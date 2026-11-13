<?php

use App\Http\Controllers\Api\TpdApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('tpd')
    ->name('api.tpd.')
    ->middleware(['tpd.api', 'throttle:120,1'])
    ->group(function () {
        Route::get('/health', [TpdApiController::class, 'health'])->name('health');
        Route::get('/catalog', [TpdApiController::class, 'catalog'])->name('catalog');
        Route::get('/enrollments', [TpdApiController::class, 'enrollments'])->name('enrollments');
        Route::post('/enroll', [TpdApiController::class, 'enroll'])->name('enroll');
        Route::get('/admin/overview', [TpdApiController::class, 'adminOverview'])->name('admin.overview');
        Route::post('/courses/{course}/publish', [TpdApiController::class, 'togglePublish'])->name('courses.publish');
    });
