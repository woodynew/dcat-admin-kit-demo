<?php

use App\Admin\Controllers\DemoController;
use App\Admin\Controllers\DemoRecordController;
use Dcat\Admin\Admin;
use Illuminate\Support\Facades\Route;

Admin::routes();

Route::group([
    'prefix' => config('admin.route.prefix'),
    'middleware' => config('admin.route.middleware'),
], function () {
    Route::get('demo/overview', [DemoController::class, 'overview'])->name('demo.overview');
    Route::get('demo/columns', [DemoController::class, 'columns'])->name('demo.columns');
    Route::get('demo/actions', [DemoController::class, 'actions'])->name('demo.actions');
    Route::get('demo/widgets', [DemoController::class, 'widgets'])->name('demo.widgets');
    Route::get('demo/features', [DemoController::class, 'features'])->name('demo.features');
    Route::get('demo/preview', [DemoController::class, 'preview'])->name('demo.preview');
    Route::get('demo/preview-form', [DemoController::class, 'previewForm'])->name('demo.preview-form');
    Route::get('demo/preview-show/{id}', [DemoController::class, 'previewShow'])->whereNumber('id')->name('demo.preview-show');
    Route::resource('demo/records', DemoRecordController::class)->names('demo.records');
    // /demo/tabs is registered by the installed iframe package, via DemoIframeServiceProvider.
});
