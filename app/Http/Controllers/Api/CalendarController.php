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
     * Get calendar data for services
     * 
     * Returns all data an SPA might need to display a calendar and time selection
     * 
     * Query Parameters:
     * - date (required) - Date in Y-m-d format (e.g., 2026-01-15)
     * - service_ids (optional) - Array of service IDs. If multiple, returns common available slots
     * 
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date|date_format:Y-m-d',
            'service_ids' => 'sometimes|array',
            'service_ids.*' => 'integer|exists:services,id',
        ]);

        $date = $request->get('date');
        $serviceIds = $request->get('service_ids', []);

        \Log::info('Calendar API Request', [
            'date' => $date,
            'service_ids' => $serviceIds,
        ]);

        // Always use getCommonSlotsForServices for consistency
        // It works for single service, multiple services, or empty array (all services)
        if (empty($serviceIds)) {
            // If no service_ids provided, get all active services
            $serviceIds = \App\Models\Service::where('is_active', true)->pluck('id')->toArray();
        }
        
        $calendarData = $this->slotGenerator->getCommonSlotsForServices($date, $serviceIds);

        \Log::info('Calendar API Response', [
            'date' => $date,
            'service_ids' => $serviceIds,
            'services_count' => count($calendarData),
        ]);

        return response()->json([
            'success' => true,
            'data' => $calendarData,
            'meta' => [
                'date' => $date,
                'service_ids' => $serviceIds,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
