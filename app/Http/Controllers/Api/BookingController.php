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
     * Create new bookings
     * Accepts multiple bookings with different services and participants
     * 
     * @param BookingRequest $request
     * @return JsonResponse
     */
    public function store(BookingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        Log::info('Booking request received', [
            'date' => $validated['date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'bookings_count' => count($validated['bookings']),
        ]);

        // Create bookings using service
        $result = $this->bookingService->createBookings($validated);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'appointments' => array_map(function ($appointment) {
                        return [
                            'id' => $appointment->id,
                            'service_id' => $appointment->service_id,
                            'service_name' => $appointment->service->name,
                            'start_time' => $appointment->start_time->toIso8601String(),
                            'end_time' => $appointment->end_time->toIso8601String(),
                            'status' => $appointment->status,
                            'participant' => [
                                'first_name' => $appointment->participants->first()->first_name,
                                'last_name' => $appointment->participants->first()->last_name,
                                'email' => $appointment->participants->first()->email,
                            ],
                        ];
                    }, $result['appointments']),
                ],
            ], 201);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'],
            'errors' => $result['errors'] ?? [],
        ], 400);
    }
}
