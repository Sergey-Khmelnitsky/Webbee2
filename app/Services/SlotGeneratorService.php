<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Service;
use Carbon\Carbon;

class SlotGeneratorService
{
    /**
     * Generate available time periods for booking on a specific date
     * Returns continuous time intervals when booking is possible
     */
    public function generateAvailableSlots(Service $service, Carbon $date): array
    {
        $debug = [];
        $debug['date'] = $date->format('Y-m-d');
        $debug['service_id'] = $service->id;
        $debug['service_name'] = $service->name;
        
        $config = $service->configuration;
        if (!$config) {
            $debug['error'] = 'No configuration found';
            \Log::info('SlotGenerator Debug', $debug);
            return [];
        }
        $debug['config'] = [
            'duration_minutes' => $config->duration_minutes,
            'break_between_minutes' => $config->break_between_minutes,
            'max_concurrent_clients' => $config->max_concurrent_clients,
            'booking_advance_days' => $config->booking_advance_days,
        ];

        if (!$this->isDateValidForBooking($date, $config)) {
            $debug['error'] = 'Date is outside booking advance days';
            $debug['max_date'] = Carbon::today()->addDays($config->booking_advance_days)->format('Y-m-d');
            \Log::info('SlotGenerator Debug', $debug);
            return [];
        }

        if ($this->isHoliday($service, $date)) {
            $debug['error'] = 'Date is a holiday';
            \Log::info('SlotGenerator Debug', $debug);
            return [];
        }

        $schedule = $this->getScheduleForDate($service, $date);
        if (!$schedule) {
            $debug['error'] = 'No schedule found for day of week: ' . $date->dayOfWeek;
            \Log::info('SlotGenerator Debug', $debug);
            return [];
        }
        $debug['schedule'] = [
            'day_of_week' => $schedule->day_of_week,
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
        ];

        [$workStart, $workEnd] = $this->parseScheduleTimes($schedule, $date);
        $debug['work_times'] = [
            'start' => $workStart->format('Y-m-d H:i'),
            'end' => $workEnd->format('Y-m-d H:i'),
        ];

        $breakPeriods = $this->getBreakPeriods($service, $date);
        $debug['break_periods_count'] = count($breakPeriods);
        $debug['break_periods'] = array_map(function ($bp) {
            return [
                'start' => $bp['start']->format('H:i'),
                'end' => $bp['end']->format('H:i'),
            ];
        }, $breakPeriods);

        $appointmentPeriods = $this->getAppointmentPeriods($service, $date);
        $debug['appointment_periods_count'] = count($appointmentPeriods);

        $blockedPeriods = $this->buildBlockedPeriods($breakPeriods, $appointmentPeriods, $workStart, $workEnd, $config);
        $debug['blocked_periods_count'] = count($blockedPeriods);

        $mergedBlocked = $this->mergeBlockedPeriods($blockedPeriods);
        $debug['merged_blocked_count'] = count($mergedBlocked);
        $debug['merged_blocked'] = array_map(function ($mb) {
            return [
                'start' => $mb['start']->format('H:i'),
                'end' => $mb['end']->format('H:i'),
            ];
        }, $mergedBlocked);

        $availablePeriods = $this->generateAvailablePeriods($mergedBlocked, $workStart, $workEnd, $config);
        $debug['available_periods_before_filter'] = $availablePeriods;
        
        $filtered = $this->filterValidPeriods($availablePeriods, $date, $config);
        $debug['available_periods_after_filter'] = $filtered;
        $debug['final_count'] = count($filtered);

        \Log::info('SlotGenerator Debug', $debug);
        
        return $filtered;
    }

    /**
     * Check if date is within booking advance days
     */
    private function isDateValidForBooking(Carbon $date, $config): bool
    {
        $today = Carbon::today();
        $maxDate = $today->copy()->addDays($config->booking_advance_days);
        // Allow booking from today up to maxDate (inclusive)
        return $date->gte($today) && !$date->isAfter($maxDate);
    }

    /**
     * Check if date is a holiday
     */
    private function isHoliday(Service $service, Carbon $date): bool
    {
        return $service->holidays()
            ->where('start_datetime', '<=', $date->copy()->endOfDay())
            ->where('end_datetime', '>=', $date->copy()->startOfDay())
            ->exists();
    }

    /**
     * Get schedule for the day of week
     */
    private function getScheduleForDate(Service $service, Carbon $date)
    {
        $dayOfWeek = $date->dayOfWeek; // 0 = Sunday, 1 = Monday, etc.
        return $service->schedules()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->first();
    }

    /**
     * Parse schedule times and return work start and end times
     */
    private function parseScheduleTimes($schedule, Carbon $date): array
    {
        $startTimeStr = $this->normalizeTimeString($schedule->start_time);
        $endTimeStr = $this->normalizeTimeString($schedule->end_time);
        
        $workStart = Carbon::parse($date->format('Y-m-d') . ' ' . $startTimeStr);
        $workEnd = Carbon::parse($date->format('Y-m-d') . ' ' . $endTimeStr);
        
        return [$workStart, $workEnd];
    }

    /**
     * Normalize time string to H:i format
     */
    private function normalizeTimeString(string $timeStr): string
    {
        if (strlen($timeStr) > 5) {
            return substr($timeStr, 0, 5); // Take only H:i part
        }
        return $timeStr;
    }

    /**
     * Get break periods for the date
     */
    private function getBreakPeriods(Service $service, Carbon $date): array
    {
        $dayOfWeek = $date->dayOfWeek;
        $breaks = $service->breaks()
            ->where(function ($query) use ($dayOfWeek) {
                $query->where('day_of_week', $dayOfWeek)
                    ->orWhereNull('day_of_week');
            })
            ->where('is_recurring', true)
            ->get();

        $breakPeriods = [];
        foreach ($breaks as $break) {
            $breakStartStr = $this->normalizeTimeString($break->start_time);
            $breakEndStr = $this->normalizeTimeString($break->end_time);
            
            $breakPeriods[] = [
                'start' => Carbon::parse($date->format('Y-m-d') . ' ' . $breakStartStr),
                'end' => Carbon::parse($date->format('Y-m-d') . ' ' . $breakEndStr),
            ];
        }

        return $breakPeriods;
    }

    /**
     * Get existing appointment periods for the date
     */
    private function getAppointmentPeriods(Service $service, Carbon $date): array
    {
        $appointments = Appointment::with('participants')
            ->where('service_id', $service->id)
            ->whereDate('start_time', $date->format('Y-m-d'))
            ->whereNull('deleted_at')
            ->get();

        $appointmentPeriods = [];
        foreach ($appointments as $appointment) {
            // Each appointment = 1 client (1 participant)
            $appointmentPeriods[] = [
                'start' => $appointment->start_time,
                'end' => $appointment->end_time,
                'participants_count' => 1, // Each appointment has exactly 1 participant
            ];
        }

        return $appointmentPeriods;
    }

    /**
     * Build blocked periods from breaks and fully booked slots
     */
    private function buildBlockedPeriods(array $breakPeriods, array $appointmentPeriods, Carbon $workStart, Carbon $workEnd, $config): array
    {
        $blockedPeriods = $breakPeriods;
        
        // Add fully booked periods (when max_concurrent_clients is reached)
        $currentTime = $workStart->copy();
        $slotInterval = $config->duration_minutes + $config->break_between_minutes;

        while ($currentTime->copy()->addMinutes($config->duration_minutes)->lte($workEnd)) {
            $slotStart = $currentTime->copy();
            $slotEnd = $currentTime->copy()->addMinutes($config->duration_minutes);

            $bookedCount = $this->countBookingsForSlot($appointmentPeriods, $slotStart, $slotEnd);

            // If slot is fully booked, add to blocked periods
            if ($bookedCount >= $config->max_concurrent_clients) {
                $blockedPeriods[] = [
                    'start' => $slotStart,
                    'end' => $slotEnd,
                ];
            }

            $currentTime->addMinutes($slotInterval);
        }

        return $blockedPeriods;
    }

    /**
     * Count bookings for a specific time slot
     * Each appointment = 1 client
     */
    private function countBookingsForSlot(array $appointmentPeriods, Carbon $slotStart, Carbon $slotEnd): int
    {
        $bookedCount = 0;
        foreach ($appointmentPeriods as $appointment) {
            // Check if appointment overlaps with this slot
            if ($appointment['start']->lt($slotEnd) && $appointment['end']->gt($slotStart)) {
                // Each appointment = 1 client
                $bookedCount += 1;
            }
        }
        return $bookedCount;
    }

    /**
     * Sort and merge overlapping blocked periods
     */
    private function mergeBlockedPeriods(array $blockedPeriods): array
    {
        // Sort blocked periods by start time
        usort($blockedPeriods, function ($a, $b) {
            if ($a['start']->eq($b['start'])) {
                return 0;
            }
            return $a['start']->gt($b['start']) ? 1 : -1;
        });

        // Merge overlapping blocked periods
        $mergedBlocked = [];
        foreach ($blockedPeriods as $blocked) {
            if (empty($mergedBlocked)) {
                $mergedBlocked[] = $blocked;
            } else {
                $last = &$mergedBlocked[count($mergedBlocked) - 1];
                if ($blocked['start']->lte($last['end'])) {
                    // Merge overlapping periods
                    if ($blocked['end']->gt($last['end'])) {
                        $last['end'] = $blocked['end'];
                    }
                } else {
                    $mergedBlocked[] = $blocked;
                }
            }
        }

        return $mergedBlocked;
    }

    /**
     * Generate available time periods from blocked periods
     */
    private function generateAvailablePeriods(array $mergedBlocked, Carbon $workStart, Carbon $workEnd, $config): array
    {
        $availablePeriods = [];
        $currentStart = $workStart->copy();

        // If no blocked periods, add one period for the entire work day
        if (empty($mergedBlocked)) {
            $latestStart = $workEnd->copy()->subMinutes($config->duration_minutes);
            if ($currentStart->lte($latestStart)) {
                $availablePeriods[] = [
                    'start_time' => $currentStart->format('H:i'),
                    'end_time' => $latestStart->format('H:i'),
                ];
            }
        } else {
            foreach ($mergedBlocked as $blocked) {
                if ($currentStart->lt($blocked['start'])) {
                    // Calculate the latest start time that allows a full appointment before the block
                    $latestStart = $blocked['start']->copy()->subMinutes($config->duration_minutes);
                    
                    // Only add period if there's enough time for at least one appointment
                    if ($currentStart->lte($latestStart)) {
                        $availablePeriods[] = [
                            'start_time' => $currentStart->format('H:i'),
                            'end_time' => $latestStart->format('H:i'),
                        ];
                    }
                }
                // Move current start to after the blocked period
                $currentStart = $blocked['end']->copy();
            }

            // Add final period if there's time after last block
            if ($currentStart->lt($workEnd)) {
                // Calculate the latest start time that allows a full appointment before work end
                $latestStart = $workEnd->copy()->subMinutes($config->duration_minutes);
                
                // Only add period if there's enough time for at least one appointment
                if ($currentStart->lte($latestStart)) {
                    $availablePeriods[] = [
                        'start_time' => $currentStart->format('H:i'),
                        'end_time' => $latestStart->format('H:i'),
                    ];
                }
            }
        }

        return $availablePeriods;
    }

    /**
     * Filter out periods that are too short for an appointment
     */
    private function filterValidPeriods(array $availablePeriods, Carbon $date, $config): array
    {
        $filtered = array_filter($availablePeriods, function ($period) use ($config, $date) {
            $start = Carbon::parse($date->format('Y-m-d') . ' ' . $period['start_time']);
            $end = Carbon::parse($date->format('Y-m-d') . ' ' . $period['end_time']);
            // Check if there's enough time for at least one appointment
            // end_time is already the latest start time, so we check if difference >= duration_minutes
            // Use absolute difference to handle both directions
            $diffMinutes = abs($end->diffInMinutes($start));
            $isValid = $diffMinutes >= $config->duration_minutes && $end->gte($start);
            
            \Log::info('Filtering period', [
                'period' => $period,
                'start' => $start->format('Y-m-d H:i'),
                'end' => $end->format('Y-m-d H:i'),
                'diff_minutes' => $diffMinutes,
                'duration_minutes' => $config->duration_minutes,
                'end_gte_start' => $end->gte($start),
                'is_valid' => $isValid,
            ]);
            
            return $isValid;
        });

        return array_values($filtered);
    }

    /**
     * Get calendar data for a specific date
     * 
     * @param string $date Date in Y-m-d format
     * @param int|null $serviceId Optional service ID to filter by
     * @return array
     */
    public function getCalendarDataForDate(string $date, ?int $serviceId = null): array
    {
        $targetDate = Carbon::parse($date);
        
        \Log::info('getCalendarDataForDate called', [
            'date' => $date,
            'service_id' => $serviceId,
            'target_date' => $targetDate->format('Y-m-d'),
            'day_of_week' => $targetDate->dayOfWeek,
        ]);
        
        $query = Service::with(['configuration', 'schedules', 'breaks', 'holidays'])
            ->where('is_active', true);

        if ($serviceId !== null) {
            $query->where('id', $serviceId);
        }

        $services = $query->get();
        
        \Log::info('Services found', [
            'count' => $services->count(),
            'service_ids' => $services->pluck('id')->toArray(),
        ]);

        $calendarData = [];

        foreach ($services as $service) {
            $config = $service->configuration;
            if (!$config) {
                \Log::info('Service skipped - no config', ['service_id' => $service->id]);
                continue;
            }

            // Check if date is within booking advance days
            $today = Carbon::today();
            $maxDate = $today->copy()->addDays($config->booking_advance_days);
            if ($targetDate->isAfter($maxDate) || $targetDate->isBefore($today)) {
                \Log::info('Service skipped - date out of range', [
                    'service_id' => $service->id,
                    'target_date' => $targetDate->format('Y-m-d'),
                    'max_date' => $maxDate->format('Y-m-d'),
                    'today' => $today->format('Y-m-d'),
                    'is_after_max' => $targetDate->isAfter($maxDate),
                    'is_before_today' => $targetDate->isBefore($today),
                ]);
                continue;
            }

            $slots = $this->generateAvailableSlots($service, $targetDate);

            $serviceData = [
                'id' => $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'configuration' => [
                    'duration_minutes' => $config->duration_minutes,
                    'break_between_minutes' => $config->break_between_minutes,
                    'max_concurrent_clients' => $config->max_concurrent_clients,
                    'booking_advance_days' => $config->booking_advance_days,
                ],
                'date' => [
                    'date' => $targetDate->format('Y-m-d'),
                    'day_of_week' => $targetDate->format('l'),
                    'day_of_week_short' => $targetDate->format('D'),
                    'slots' => $slots,
                    'has_available_slots' => !empty($slots),
                ],
            ];

            $calendarData[] = $serviceData;
        }

        return $calendarData;
    }
}
