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
        $config = $service->configuration;
        if (!$config) {
            return [];
        }

        // Check if date is within booking advance days
        $maxDate = Carbon::today()->addDays($config->booking_advance_days);
        if ($date->isAfter($maxDate)) {
            return [];
        }

        // Check if date is a holiday
        $isHoliday = $service->holidays()
            ->where('start_datetime', '<=', $date->copy()->endOfDay())
            ->where('end_datetime', '>=', $date->copy()->startOfDay())
            ->exists();

        if ($isHoliday) {
            return [];
        }

        // Get schedule for the day of week
        $dayOfWeek = $date->dayOfWeek; // 0 = Sunday, 1 = Monday, etc.
        $schedule = $service->schedules()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->first();

        if (!$schedule) {
            return [];
        }

        // Parse schedule times
        $workStart = Carbon::parse($date->format('Y-m-d') . ' ' . $schedule->start_time);
        $workEnd = Carbon::parse($date->format('Y-m-d') . ' ' . $schedule->end_time);

        // Get breaks for this day
        $breaks = $service->breaks()
            ->where(function ($query) use ($dayOfWeek) {
                $query->where('day_of_week', $dayOfWeek)
                    ->orWhereNull('day_of_week');
            })
            ->where('is_recurring', true)
            ->get();

        $breakPeriods = [];
        foreach ($breaks as $break) {
            $breakPeriods[] = [
                'start' => Carbon::parse($date->format('Y-m-d') . ' ' . $break->start_time),
                'end' => Carbon::parse($date->format('Y-m-d') . ' ' . $break->end_time),
            ];
        }

        // Get existing appointments for this date
        $appointments = Appointment::with('participants')
            ->where('service_id', $service->id)
            ->whereDate('start_time', $date->format('Y-m-d'))
            ->whereNull('deleted_at')
            ->get();

        $appointmentPeriods = [];
        foreach ($appointments as $appointment) {
            $appointmentPeriods[] = [
                'start' => $appointment->start_time,
                'end' => $appointment->end_time,
                'participants_count' => $appointment->participants->count(),
            ];
        }

        // Build blocked periods: breaks + fully booked time slots
        $blockedPeriods = $breakPeriods;
        
        // Add fully booked periods (when max_concurrent_clients is reached)
        $currentTime = $workStart->copy();
        $slotInterval = $config->duration_minutes + $config->break_between_minutes;

        while ($currentTime->copy()->addMinutes($config->duration_minutes)->lte($workEnd)) {
            $slotStart = $currentTime->copy();
            $slotEnd = $currentTime->copy()->addMinutes($config->duration_minutes);

            // Count bookings for this slot
            $bookedCount = 0;
            foreach ($appointmentPeriods as $appointment) {
                // Check if appointment overlaps with this slot
                if ($appointment['start']->lt($slotEnd) && $appointment['end']->gt($slotStart)) {
                    $bookedCount += $appointment['participants_count'];
                }
            }

            // If slot is fully booked, add to blocked periods
            if ($bookedCount >= $config->max_concurrent_clients) {
                $blockedPeriods[] = [
                    'start' => $slotStart,
                    'end' => $slotEnd,
                ];
            }

            $currentTime->addMinutes($slotInterval);
        }

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

        // Generate available time periods (work time minus blocked periods)
        // But adjust end times to account for duration_minutes
        $availablePeriods = [];
        $currentStart = $workStart->copy();

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

        // Filter out periods that are too short for an appointment
        // Note: end_time is already adjusted to be the latest start time, so we need to check
        // if there's at least duration_minutes between start and end
        $availablePeriods = array_filter($availablePeriods, function ($period) use ($config, $date) {
            $start = Carbon::parse($date->format('Y-m-d') . ' ' . $period['start_time']);
            $end = Carbon::parse($date->format('Y-m-d') . ' ' . $period['end_time']);
            // Check if there's enough time for at least one appointment
            // end_time is already the latest start time, so we check if difference >= duration_minutes
            return $end->diffInMinutes($start) >= $config->duration_minutes;
        });

        return array_values($availablePeriods);
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
        
        $query = Service::with(['configuration', 'schedules', 'breaks', 'holidays'])
            ->where('is_active', true);

        if ($serviceId !== null) {
            $query->where('id', $serviceId);
        }

        $services = $query->get();

        $calendarData = [];

        foreach ($services as $service) {
            $config = $service->configuration;
            if (!$config) {
                continue;
            }

            // Check if date is within booking advance days
            $maxDate = Carbon::today()->addDays($config->booking_advance_days);
            if ($targetDate->isAfter($maxDate) || $targetDate->isBefore(Carbon::today())) {
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
