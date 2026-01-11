<?php

use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CalendarController;
use Illuminate\Support\Facades\Route;

Route::get('/calendar', [CalendarController::class, 'index']);
Route::post('/bookings', [BookingController::class, 'store']);