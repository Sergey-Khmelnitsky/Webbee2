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
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $daysAhead = (int) $request->get('days_ahead', 7);

        $calendarData = $this->slotGenerator->getCalendarData($daysAhead);

        return response()->json([
            'success' => true,
            'data' => $calendarData,
            'meta' => [
                'days_ahead' => $daysAhead,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
