<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    /**
     * Show the booking form
     */
    public function index()
    {
        $services = Service::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('booking.index', [
            'services' => $services,
        ]);
    }
}
