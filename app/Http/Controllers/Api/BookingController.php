<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingRequest;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
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
     * @param BookingRequest $request
     * @return JsonResponse
     */
    public function store(BookingRequest $request): JsonResponse
    {
        $validated = $request->validated();

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
