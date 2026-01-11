<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Service;
use Carbon\Carbon;

class SlotGeneratorService
{
    /**
     * Generate available slots for a service on a specific date
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
        $startTime = Carbon::parse($date->format('Y-m-d') . ' ' . $schedule->start_time);
        $endTime = Carbon::parse($date->format('Y-m-d') . ' ' . $schedule->end_time);

        // Get breaks for this day
        $breaks = $service->breaks()
            ->where(function ($query) use ($dayOfWeek) {
                $query->where('day_of_week', $dayOfWeek)
                    ->orWhereNull('day_of_week');
            })
            ->where('is_recurring', true)
            ->get();

        // Get existing appointments for this date
        $existingAppointments = Appointment::with('participants')
            ->where('service_id', $service->id)
            ->whereDate('start_time', $date->format('Y-m-d'))
            ->whereNull('deleted_at')
            ->get()
            ->groupBy(function ($appointment) {
                return $appointment->start_time->format('H:i');
            });

        // Generate slots
        // Calculate slot interval: duration + break_between
        // This ensures slots start at fixed intervals (e.g., every 10 minutes)
        $slotInterval = $config->duration_minutes + $config->break_between_minutes;
        
        $slots = [];
        $currentTime = $startTime->copy();

        while ($currentTime->copy()->addMinutes($config->duration_minutes)->lte($endTime)) {
            $slotStart = $currentTime->copy();
            $slotEnd = $currentTime->copy()->addMinutes($config->duration_minutes);

            // Check if slot overlaps with any break
            $isInBreak = $breaks->contains(function ($break) use ($slotStart, $slotEnd) {
                $breakStart = Carbon::parse($slotStart->format('Y-m-d') . ' ' . $break->start_time);
                $breakEnd = Carbon::parse($slotStart->format('Y-m-d') . ' ' . $break->end_time);
                // Check if slot overlaps with break
                return $slotStart->lt($breakEnd) && $slotEnd->gt($breakStart);
            });

            if (!$isInBreak) {
                // Check how many clients are already booked for this exact time slot
                $slotKey = $slotStart->format('H:i');
                $bookedCount = $existingAppointments->get($slotKey, collect())->sum(function ($appointment) {
                    return $appointment->participants->count();
                });

                $availableCount = $config->max_concurrent_clients - $bookedCount;

                if ($availableCount > 0) {
                    $slots[] = [
                        'start_time' => $slotStart->format('H:i'),
                        'end_time' => $slotEnd->format('H:i'),
                        'available_count' => $availableCount,
                        'is_available' => true,
                    ];
                }
            }

            // Move to next slot start time (slot interval)
            $currentTime->addMinutes($slotInterval);
        }

        return $slots;
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
