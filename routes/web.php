<?php

use App\Http\Controllers\DemoEntryController;
use App\Models\DemoState;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::get('/', function () {
    $lastResetAt = Schema::hasTable('demo_states') ? DemoState::where('key', 'last_reset_at')->value('value') : null;

    return view('demo.welcome', compact('lastResetAt'));
})->name('home');
Route::post('/demo/enter', DemoEntryController::class)->middleware('throttle:demo-login')->name('demo.enter');
Route::redirect('/admin', '/admin/demo/overview');
