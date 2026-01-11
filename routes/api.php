<?php

use App\Http\Controllers\Api\CalendarController;
use Illuminate\Support\Facades\Route;

Route::get('/calendar', [CalendarController::class, 'index']);
