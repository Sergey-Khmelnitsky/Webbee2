<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    /**
     * Create a new booking
     * 
     * Accepts booking data and participant details
     * TODO: Implement actual booking logic
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        // Validate request
        $request->validate([
            'service_id' => 'required|integer|exists:services,id',
            'date' => 'required|date|date_format:Y-m-d',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'participants' => 'required|array|min:1',
            'participants.*.first_name' => 'required|string|max:255',
            'participants.*.last_name' => 'required|string|max:255',
            'participants.*.email' => 'required|email|max:255',
        ]);

        // Log the booking request (for now, we just log it)
        Log::info('Booking request received', [
            'service_id' => $request->get('service_id'),
            'date' => $request->get('date'),
            'start_time' => $request->get('start_time'),
            'end_time' => $request->get('end_time'),
            'participants_count' => count($request->get('participants')),
            'participants' => $request->get('participants'),
        ]);

        // TODO: Implement actual booking logic
        // - Validate slot availability
        // - Check max_concurrent_clients
        // - Create appointment
        // - Create participants
        // - Return success/error response

        return response()->json([
            'success' => true,
            'message' => 'Booking request received (not yet processed)',
            'data' => [
                'service_id' => $request->get('service_id'),
                'date' => $request->get('date'),
                'start_time' => $request->get('start_time'),
                'end_time' => $request->get('end_time'),
                'participants' => $request->get('participants'),
            ],
        ], 201);
    }
}
