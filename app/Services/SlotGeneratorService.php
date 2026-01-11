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
        $slotInterval = $this->getSlotInterval($config);

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
     * 
     * Returns periods where end_time is the last possible end time (not start time)
     */
    private function generateAvailablePeriods(array $mergedBlocked, Carbon $workStart, Carbon $workEnd, $config): array
    {
        $availablePeriods = [];
        $currentStart = $workStart->copy();

        // If no blocked periods, add one period for the entire work day
        if (empty($mergedBlocked)) {
            // end_time should be workEnd (last possible end time)
            // but we need to ensure there's at least one slot possible
            $earliestEnd = $currentStart->copy()->addMinutes($config->duration_minutes);
            if ($earliestEnd->lte($workEnd)) {
                $availablePeriods[] = [
                    'start_time' => $currentStart->format('H:i'),
                    'end_time' => $workEnd->format('H:i'),
                ];
            }
        } else {
            foreach ($mergedBlocked as $blocked) {
                if ($currentStart->lt($blocked['start'])) {
                    // end_time should be blocked start (last possible end time before block)
                    // but we need to ensure there's at least one slot possible
                    $earliestEnd = $currentStart->copy()->addMinutes($config->duration_minutes);
                    if ($earliestEnd->lte($blocked['start'])) {
                        $availablePeriods[] = [
                            'start_time' => $currentStart->format('H:i'),
                            'end_time' => $blocked['start']->format('H:i'),
                        ];
                    }
                }
                // Move current start to after the blocked period
                $currentStart = $blocked['end']->copy();
            }

            // Add final period if there's time after last block
            if ($currentStart->lt($workEnd)) {
                // end_time should be workEnd (last possible end time)
                // but we need to ensure there's at least one slot possible
                $earliestEnd = $currentStart->copy()->addMinutes($config->duration_minutes);
                if ($earliestEnd->lte($workEnd)) {
                    $availablePeriods[] = [
                        'start_time' => $currentStart->format('H:i'),
                        'end_time' => $workEnd->format('H:i'),
                    ];
                }
            }
        }

        return $availablePeriods;
    }

    /**
     * Filter out periods that are too short for an appointment
     * 
     * Now end_time is the last possible end time, so we check if there's enough time
     * for at least one appointment (start + duration <= end)
     */
    private function filterValidPeriods(array $availablePeriods, Carbon $date, $config): array
    {
        $filtered = array_filter($availablePeriods, function ($period) use ($config, $date) {
            $start = Carbon::parse($date->format('Y-m-d') . ' ' . $period['start_time']);
            $end = Carbon::parse($date->format('Y-m-d') . ' ' . $period['end_time']);
            
            // Check if there's enough time for at least one appointment
            // end_time is now the last possible end time, so we check if start + duration <= end
            $earliestEnd = $start->copy()->addMinutes($config->duration_minutes);
            $isValid = $earliestEnd->lte($end);
            
            \Log::info('Filtering period', [
                'period' => $period,
                'start' => $start->format('Y-m-d H:i'),
                'end' => $end->format('Y-m-d H:i'),
                'earliest_end' => $earliestEnd->format('Y-m-d H:i'),
                'duration_minutes' => $config->duration_minutes,
                'is_valid' => $isValid,
            ]);
            
            return $isValid;
        });

        return array_values($filtered);
    }

    /**
     * Get common available slots for multiple services
     * Returns slots that are available for ALL specified services simultaneously
     * 
     * @param string $date Date in Y-m-d format
     * @param array $serviceIds Array of service IDs (may contain duplicates)
     * @return array Combined calendar data with common slots
     */
    public function getCommonSlotsForServices(string $date, array $serviceIds): array
    {
        $targetDate = Carbon::parse($date);
        
        // Count occurrences of each service ID
        $serviceCounts = array_count_values($serviceIds);
        $uniqueServiceIds = array_unique($serviceIds);
        
        \Log::info('getCommonSlotsForServices called', [
            'date' => $date,
            'service_ids' => $serviceIds,
            'service_counts' => $serviceCounts,
            'target_date' => $targetDate->format('Y-m-d'),
        ]);

        // Load all services with relations
        $services = Service::with(['configuration', 'schedules', 'breaks', 'holidays'])
            ->whereIn('id', $uniqueServiceIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        if ($services->isEmpty()) {
            return [];
        }

        // Get available periods for each service instance (considering duplicates)
        $servicePeriods = [];
        $serviceConfigs = [];
        
        foreach ($serviceIds as $serviceId) {
            if (!isset($services[$serviceId])) {
                continue;
            }

            $service = $services[$serviceId];
            $config = $service->configuration;
            if (!$config) {
                continue;
            }

            // Check if date is valid for this service
            $today = Carbon::today();
            $maxDate = $today->copy()->addDays($config->booking_advance_days);
            if ($targetDate->isAfter($maxDate) || $targetDate->isBefore($today)) {
                continue;
            }

            // Check if holiday
            if ($this->isHoliday($service, $targetDate)) {
                continue;
            }

            $periods = $this->generateAvailableSlots($service, $targetDate);
            if (!empty($periods)) {
                // Use service ID as key, but we'll process each instance separately
                $servicePeriods[] = $periods;
                $serviceConfigs[] = [
                    'service' => $service,
                    'config' => $config,
                    'service_id' => $serviceId,
                ];
            }
        }

        if (empty($servicePeriods)) {
            return [];
        }

        // Find intersection of all periods (including duplicates)
        $commonPeriods = $this->findCommonPeriodsForInstances($servicePeriods, $serviceConfigs, $targetDate);

        // Filter slots by max_concurrent_clients for each service
        $filteredPeriods = $this->filterSlotsByMaxConcurrentClients(
            $commonPeriods, 
            $serviceCounts, 
            $services, 
            $targetDate
        );

        // Return single result with common slots for all services
        if (empty($filteredPeriods)) {
            return [];
        }

        // Build service info with counts
        $serviceInfo = [];
        foreach ($serviceCounts as $serviceId => $count) {
            if (isset($services[$serviceId])) {
                $service = $services[$serviceId];
                $serviceInfo[] = [
                    'id' => $service->id,
                    'name' => $service->name,
                    'count' => $count,
                ];
            }
        }

        // Use maximum duration (longest service) and minimum break for common slots
        $allConfigs = array_column($serviceConfigs, 'config');
        $maxDuration = max(array_map(fn($c) => $c->duration_minutes, $allConfigs));
        $minBreak = min(array_map(fn($c) => $c->break_between_minutes, $allConfigs));
        $minMaxClients = min(array_map(fn($c) => $c->max_concurrent_clients, $allConfigs));
        $minAdvanceDays = min(array_map(fn($c) => $c->booking_advance_days, $allConfigs));

        // Build name with counts
        $serviceNames = array_map(function($info) {
            if ($info['count'] > 1) {
                return $info['name'] . ' (x' . $info['count'] . ')';
            }
            return $info['name'];
        }, $serviceInfo);

        return [
            [
                'id' => implode(',', $serviceIds), // All IDs including duplicates
                'name' => implode(' & ', $serviceNames), // Combined names with counts
                'description' => 'Common available slots for selected services',
                'service_ids' => $serviceIds, // All IDs including duplicates
                'services' => $serviceInfo,
                'configuration' => [
                    'duration_minutes' => $maxDuration,
                    'break_between_minutes' => $minBreak,
                    'max_concurrent_clients' => $minMaxClients,
                    'booking_advance_days' => $minAdvanceDays,
                ],
                'date' => [
                    'date' => $targetDate->format('Y-m-d'),
                    'day_of_week' => $targetDate->format('l'),
                    'day_of_week_short' => $targetDate->format('D'),
                    'slots' => $filteredPeriods,
                    'has_available_slots' => !empty($filteredPeriods),
                ],
            ],
        ];
    }

    /**
     * Find common time periods across multiple service instances (including duplicates)
     * 
     * @param array $servicePeriods Array of [periods] for each service instance
     * @param array $serviceConfigs Array of [service, config, service_id] for each instance
     * @param Carbon $date
     * @return array Common available periods
     */
    private function findCommonPeriodsForInstances(array $servicePeriods, array $serviceConfigs, Carbon $date): array
    {
        if (empty($servicePeriods)) {
            return [];
        }

        // Convert periods to time ranges for easier comparison
        $allRanges = [];
        foreach ($servicePeriods as $periods) {
            $ranges = [];
            foreach ($periods as $period) {
                $start = Carbon::parse($date->format('Y-m-d') . ' ' . $period['start_time']);
                $end = Carbon::parse($date->format('Y-m-d') . ' ' . $period['end_time']);
                $ranges[] = ['start' => $start, 'end' => $end];
            }
            $allRanges[] = $ranges;
        }

        // Find intersection of all ranges
        $commonRanges = $this->findIntersectionOfRanges($allRanges);

        // Generate slots from common ranges
        $commonPeriods = $this->generateSlotsFromRanges($commonRanges, $serviceConfigs);

        // Merge overlapping periods
        return $this->mergePeriods($commonPeriods);
    }

    /**
     * Find intersection of time ranges across all services
     * 
     * @param array $allRanges Array of [service_id => [ranges]]
     * @return array Common ranges that intersect across all services
     */
    private function findIntersectionOfRanges(array $allRanges): array
    {
        if (empty($allRanges)) {
            return [];
        }

        $commonRanges = [];
        $firstServiceRanges = array_shift($allRanges);

        foreach ($firstServiceRanges as $range) {
            $intersection = $this->findRangeIntersection($range, $allRanges);
            if ($intersection !== null) {
                $commonRanges[] = $intersection;
            }
        }

        return $commonRanges;
    }

    /**
     * Find intersection of a range with all other service ranges
     * 
     * @param array $range ['start' => Carbon, 'end' => Carbon]
     * @param array $allRanges Array of [service_id => [ranges]]
     * @return array|null Intersection range or null if no intersection
     */
    private function findRangeIntersection(array $range, array $allRanges): ?array
    {
        $intersectionStart = $range['start'];
        $intersectionEnd = $range['end'];

        // Check if this range intersects with all other services
        foreach ($allRanges as $otherRanges) {
            $intersection = $this->findIntersectionWithRanges($intersectionStart, $intersectionEnd, $otherRanges);
            if ($intersection === null) {
                return null; // No intersection with this service
            }
            
            $intersectionStart = $intersection['start'];
            $intersectionEnd = $intersection['end'];
        }

        if ($intersectionStart->lt($intersectionEnd)) {
            return [
                'start' => $intersectionStart,
                'end' => $intersectionEnd,
            ];
        }

        return null;
    }

    /**
     * Find intersection of a time range with a list of ranges
     * 
     * @param Carbon $start Start time
     * @param Carbon $end End time
     * @param array $ranges Array of ['start' => Carbon, 'end' => Carbon]
     * @return array|null Intersection range or null if no intersection
     */
    private function findIntersectionWithRanges(Carbon $start, Carbon $end, array $ranges): ?array
    {
        foreach ($ranges as $otherRange) {
            // Check if ranges overlap
            if ($start->lt($otherRange['end']) && $end->gt($otherRange['start'])) {
                // Calculate actual intersection
                $actualStart = $start->gt($otherRange['start']) ? $start : $otherRange['start'];
                $actualEnd = $end->lt($otherRange['end']) ? $end : $otherRange['end'];
                
                if ($actualStart->lt($actualEnd)) {
                    return [
                        'start' => $actualStart,
                        'end' => $actualEnd,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Generate time slots from common ranges
     * 
     * @param array $commonRanges Array of ['start' => Carbon, 'end' => Carbon]
     * @param array $serviceConfigs Array of [service_id => [service, config]]
     * @return array Array of periods with start_time and end_time
     */
    private function generateSlotsFromRanges(array $commonRanges, array $serviceConfigs): array
    {
        if (empty($commonRanges)) {
            return [];
        }

        // Use maximum duration (longest service) to ensure all services can fit
        // Use minimum slot_interval_minutes from all services, or fallback to duration + break
        $maxDuration = max(array_map(fn($sc) => $sc['config']->duration_minutes, $serviceConfigs));
        
        // Calculate slot intervals for all services
        $slotIntervals = array_map(fn($sc) => $this->getSlotInterval($sc['config']), $serviceConfigs);
        $minSlotInterval = min($slotIntervals);

        $commonPeriods = [];
        foreach ($commonRanges as $range) {
            $slots = $this->generateSlotsInRange($range, $maxDuration, $minSlotInterval);
            $commonPeriods = array_merge($commonPeriods, $slots);
        }

        return $commonPeriods;
    }

    /**
     * Generate time slots within a time range
     * 
     * @param array $range ['start' => Carbon, 'end' => Carbon]
     * @param int $durationMinutes Slot duration in minutes
     * @param int $slotIntervalMinutes Interval between slot start times in minutes
     * @return array Array of periods with start_time and end_time
     */
    private function generateSlotsInRange(array $range, int $durationMinutes, int $slotIntervalMinutes): array
    {
        $slots = [];
        $currentStart = $range['start']->copy();

        while ($currentStart->copy()->addMinutes($durationMinutes)->lte($range['end'])) {
            $slotEnd = $currentStart->copy()->addMinutes($durationMinutes);
            
            $slots[] = [
                'start_time' => $currentStart->format('H:i'),
                'end_time' => $slotEnd->format('H:i'),
            ];

            $currentStart->addMinutes($slotIntervalMinutes);
        }

        return $slots;
    }

    /**
     * Merge overlapping or adjacent periods
     */
    private function mergePeriods(array $periods): array
    {
        if (empty($periods)) {
            return [];
        }

        // Sort by start_time
        usort($periods, function ($a, $b) {
            return strcmp($a['start_time'], $b['start_time']);
        });

        $merged = [];
        $current = $periods[0];

        for ($i = 1; $i < count($periods); $i++) {
            $next = $periods[$i];
            
            // If periods overlap or are adjacent, merge them
            if ($current['end_time'] >= $next['start_time']) {
                $current['end_time'] = max($current['end_time'], $next['end_time']);
            } else {
                $merged[] = $current;
                $current = $next;
            }
        }

        $merged[] = $current;
        return $merged;
    }

    /**
     * Filter slots by max_concurrent_clients for each service
     * Checks that each slot has enough capacity for all requested service instances
     * 
     * @param array $periods Array of periods with start_time and end_time
     * @param array $serviceCounts Array of [service_id => count]
     * @param \Illuminate\Database\Eloquent\Collection $services Collection of Service models
     * @param Carbon $date
     * @return array Filtered periods (only periods where at least one slot has capacity)
     */
    private function filterSlotsByMaxConcurrentClients(
        array $periods, 
        array $serviceCounts, 
        $services, 
        Carbon $date
    ): array {
        if (empty($periods)) {
            return [];
        }

        $filtered = [];
        
        foreach ($periods as $period) {
            $periodStart = Carbon::parse($date->format('Y-m-d') . ' ' . $period['start_time']);
            $periodEnd = Carbon::parse($date->format('Y-m-d') . ' ' . $period['end_time']);
            
            // Find maximum duration (longest service) and minimum slot interval from all services
            $maxDuration = null;
            $slotIntervals = [];
            foreach ($serviceCounts as $serviceId => $count) {
                if (isset($services[$serviceId])) {
                    $config = $services[$serviceId]->configuration;
                    if ($config) {
                        if ($maxDuration === null || $config->duration_minutes > $maxDuration) {
                            $maxDuration = $config->duration_minutes;
                        }
                        $slotIntervals[] = $this->getSlotInterval($config);
                    }
                }
            }
            
            if ($maxDuration === null || empty($slotIntervals)) {
                continue;
            }
            
            // Use minimum slot interval from all services
            $minSlotInterval = min($slotIntervals);
            
            // Generate all possible slots in this period using maximum duration
            $currentSlotStart = $periodStart->copy();
            $hasAvailableSlot = false;
            
            while ($currentSlotStart->copy()->addMinutes($maxDuration)->lte($periodEnd)) {
                $slotEnd = $currentSlotStart->copy()->addMinutes($maxDuration);
                
                // Check if this specific slot has capacity for all requested services
                $slotHasCapacity = true;
                
                foreach ($serviceCounts as $serviceId => $requestedCount) {
                    if (!isset($services[$serviceId])) {
                        $slotHasCapacity = false;
                        break;
                    }
                    
                    $service = $services[$serviceId];
                    $config = $service->configuration;
                    
                    if (!$config) {
                        $slotHasCapacity = false;
                        break;
                    }
                    
                    // Count existing bookings for this service at this exact time slot
                    $existingBookings = Appointment::where('service_id', $serviceId)
                        ->where('start_time', $currentSlotStart)
                        ->whereNull('deleted_at')
                        ->count();
                    
                    // Check if there's enough capacity
                    $availableCapacity = $config->max_concurrent_clients - $existingBookings;
                    
                    if ($availableCapacity < $requestedCount) {
                        $slotHasCapacity = false;
                        break;
                    }
                }
                
                if ($slotHasCapacity) {
                    $hasAvailableSlot = true;
                    break; // At least one slot in this period has capacity
                }
                
                $currentSlotStart->addMinutes($minSlotInterval);
            }
            
            // Only include period if it has at least one available slot
            if ($hasAvailableSlot) {
                $filtered[] = $period;
            }
        }
        
        return $filtered;
    }

    /**
     * Get slot interval for a service configuration
     * Uses slot_interval_minutes if set, otherwise falls back to duration_minutes + break_between_minutes
     * 
     * @param $config ServiceConfiguration
     * @return int Slot interval in minutes
     */
    private function getSlotInterval($config): int
    {
        if ($config->slot_interval_minutes !== null) {
            return $config->slot_interval_minutes;
        }
        
        return $config->duration_minutes + $config->break_between_minutes;
    }
}
