<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SlotGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function __construct(
        private SlotGeneratorService $slotGenerator
    ) {
    }

    /**
     * Get calendar data for all services
     * 
     * Returns all data an SPA might need to display a calendar and time selection
     * 
     * Query Parameters:
     * - date (required) - Date in Y-m-d format (e.g., 2026-01-15)
     * - service_id (optional) - Filter by specific service ID
     * 
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date|date_format:Y-m-d',
            'service_id' => 'sometimes|integer|exists:services,id',
        ]);

        $date = $request->get('date');
        $serviceId = $request->get('service_id');

        \Log::info('Calendar API Request', [
            'date' => $date,
            'service_id' => $serviceId,
        ]);

        $calendarData = $this->slotGenerator->getCalendarDataForDate($date, $serviceId);

        \Log::info('Calendar API Response', [
            'date' => $date,
            'service_id' => $serviceId,
            'services_count' => count($calendarData),
            'data' => $calendarData,
        ]);

        return response()->json([
            'success' => true,
            'data' => $calendarData,
            'meta' => [
                'date' => $date,
                'service_id' => $serviceId,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
