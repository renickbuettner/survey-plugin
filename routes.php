<?php

use Illuminate\Support\Facades\Route;
use Renick\Survey\Classes\Content\EmbedController;

// Endpoints of the survey block for Renick.Content (see classes/content); harmless without it.
Route::group(['prefix' => 'renick-survey', 'middleware' => ['web']], function () {
    Route::get('embed/{id}', [EmbedController::class, 'show'])->where('id', '[0-9]+|[0-9a-fA-F-]{36}');
    Route::post('embed/{id}', [EmbedController::class, 'submit'])->where('id', '[0-9]+|[0-9a-fA-F-]{36}');
});
