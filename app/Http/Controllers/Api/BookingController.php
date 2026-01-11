<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    public function __construct(
        private BookingService $bookingService
    ) {
    }

    /**
     * Create a new booking
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        // Validate request
        $validated = $request->validate([
            'service_id' => 'required|integer|exists:services,id',
            'date' => 'required|date|date_format:Y-m-d',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'participants' => 'required|array|min:1|max:1',
            'participants.*.first_name' => 'required|string|max:255',
            'participants.*.last_name' => 'required|string|max:255',
            'participants.*.email' => 'required|email|max:255',
        ]);

        Log::info('Booking request received', [
            'service_id' => $validated['service_id'],
            'date' => $validated['date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'participants_count' => count($validated['participants']),
        ]);

        // Create booking using service
        $result = $this->bookingService->createBooking($validated);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'appointment' => [
                        'id' => $result['appointment']->id,
                        'service_id' => $result['appointment']->service_id,
                        'service_name' => $result['appointment']->service->name,
                        'start_time' => $result['appointment']->start_time->toIso8601String(),
                        'end_time' => $result['appointment']->end_time->toIso8601String(),
                        'status' => $result['appointment']->status,
                        'participant' => [
                            'first_name' => $result['appointment']->participants->first()->first_name,
                            'last_name' => $result['appointment']->participants->first()->last_name,
                            'email' => $result['appointment']->participants->first()->email,
                        ],
                    ],
                ],
            ], 201);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'],
        ], 400);
    }
}
