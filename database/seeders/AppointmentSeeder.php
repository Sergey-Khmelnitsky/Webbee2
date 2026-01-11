<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\AppointmentParticipant;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AppointmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $menHaircut = Service::where('name', 'Men Haircut')->first();
        $womenHaircut = Service::where('name', 'Women Haircut')->first();

        if (!$menHaircut || !$womenHaircut) {
            $this->command->error('Services not found. Please run ServiceSeeder and ServiceConfigurationSeeder first.');
            return;
        }

        // Generate appointments for the next 7 days
        $today = Carbon::today();
        for ($i = 0; $i < 7; $i++) {
            $date = $today->copy()->addDays($i);
            
            // Skip Sunday (day_of_week = 0)
            if ($date->dayOfWeek === 0) {
                continue;
            }

            // Skip holiday (3rd day from today)
            if ($i === 3) {
                continue;
            }

            // Generate appointments for Men Haircut
            $this->generateAppointmentsForService($menHaircut, $date, [
                ['first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john.doe@example.com'],
                ['first_name' => 'Jane', 'last_name' => 'Smith', 'email' => 'jane.smith@example.com'],
                ['first_name' => 'Bob', 'last_name' => 'Johnson', 'email' => 'bob.johnson@example.com'],
            ]);

            // Generate appointments for Women Haircut
            $this->generateAppointmentsForService($womenHaircut, $date, [
                ['first_name' => 'Alice', 'last_name' => 'Williams', 'email' => 'alice.williams@example.com'],
                ['first_name' => 'Emma', 'last_name' => 'Brown', 'email' => 'emma.brown@example.com'],
            ]);
        }
    }

    private function generateAppointmentsForService(Service $service, Carbon $date, array $participantsPool): void
    {
        $config = $service->configuration;
        if (!$config) {
            return;
        }

        // Get schedule for the day
        $dayOfWeek = $date->dayOfWeek;
        $schedule = $service->schedules()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->first();

        if (!$schedule) {
            return;
        }

        // Parse schedule times
        $startTimeStr = $this->normalizeTimeString($schedule->start_time);
        $endTimeStr = $this->normalizeTimeString($schedule->end_time);
        $workStart = Carbon::parse($date->format('Y-m-d') . ' ' . $startTimeStr);
        $workEnd = Carbon::parse($date->format('Y-m-d') . ' ' . $endTimeStr);

        // Get breaks for this day
        $breaks = $service->breaks()
            ->where(function ($query) use ($dayOfWeek) {
                $query->where('day_of_week', $dayOfWeek)
                    ->orWhereNull('day_of_week');
            })
            ->where('is_recurring', true)
            ->get();

        // Generate valid time slots
        $validSlots = $this->generateValidSlots($workStart, $workEnd, $breaks, $config);

        // Create appointments (not more than max_concurrent_clients per slot)
        $slotInterval = $config->duration_minutes + $config->break_between_minutes;
        $currentTime = $workStart->copy();
        $participantIndex = 0;

        while ($currentTime->copy()->addMinutes($config->duration_minutes)->lte($workEnd)) {
            $slotStart = $currentTime->copy();
            $slotEnd = $currentTime->copy()->addMinutes($config->duration_minutes);

            // Check if slot is valid (not in break, not in holiday)
            if (!$this->isSlotValid($slotStart, $slotEnd, $breaks, $service, $date)) {
                $currentTime->addMinutes($slotInterval);
                continue;
            }

            // Randomly decide if we create an appointment for this slot (30% chance)
            if (rand(1, 100) <= 30) {
                // Random number of participants (1 to min(3, available in pool))
                $numParticipants = rand(1, min(3, count($participantsPool)));
                
                // Check existing appointments for this slot
                $existingCount = Appointment::where('service_id', $service->id)
                    ->where('start_time', $slotStart)
                    ->whereNull('deleted_at')
                    ->withCount('participants')
                    ->get()
                    ->sum('participants_count');

                // Only create if we don't exceed max_concurrent_clients
                if ($existingCount + $numParticipants <= $config->max_concurrent_clients) {
                    $appointment = Appointment::create([
                        'service_id' => $service->id,
                        'created_by_user_id' => null, // Client booking
                        'start_time' => $slotStart,
                        'end_time' => $slotEnd,
                        'status' => $this->randomStatus(),
                        'notes' => null,
                    ]);

                    // Create participants
                    for ($i = 0; $i < $numParticipants; $i++) {
                        $participant = $participantsPool[$participantIndex % count($participantsPool)];
                        AppointmentParticipant::create([
                            'appointment_id' => $appointment->id,
                            'first_name' => $participant['first_name'],
                            'last_name' => $participant['last_name'],
                            'email' => $participant['email'],
                        ]);
                        $participantIndex++;
                    }
                }
            }

            $currentTime->addMinutes($slotInterval);
        }
    }

    private function normalizeTimeString(string $timeStr): string
    {
        if (strlen($timeStr) > 5) {
            return substr($timeStr, 0, 5);
        }
        return $timeStr;
    }

    private function generateValidSlots(Carbon $workStart, Carbon $workEnd, $breaks, $config): array
    {
        $validSlots = [];
        $slotInterval = $config->duration_minutes + $config->break_between_minutes;
        $currentTime = $workStart->copy();

        while ($currentTime->copy()->addMinutes($config->duration_minutes)->lte($workEnd)) {
            $slotStart = $currentTime->copy();
            $slotEnd = $currentTime->copy()->addMinutes($config->duration_minutes);

            // Check if slot is in a break
            $isInBreak = false;
            foreach ($breaks as $break) {
                $breakStart = Carbon::parse($slotStart->format('Y-m-d') . ' ' . $this->normalizeTimeString($break->start_time));
                $breakEnd = Carbon::parse($slotStart->format('Y-m-d') . ' ' . $this->normalizeTimeString($break->end_time));
                if ($slotStart->lt($breakEnd) && $slotEnd->gt($breakStart)) {
                    $isInBreak = true;
                    break;
                }
            }

            if (!$isInBreak) {
                $validSlots[] = [
                    'start' => $slotStart,
                    'end' => $slotEnd,
                ];
            }

            $currentTime->addMinutes($slotInterval);
        }

        return $validSlots;
    }

    private function isSlotValid(Carbon $slotStart, Carbon $slotEnd, $breaks, Service $service, Carbon $date): bool
    {
        // Check if date is a holiday
        $isHoliday = $service->holidays()
            ->where('start_datetime', '<=', $date->copy()->endOfDay())
            ->where('end_datetime', '>=', $date->copy()->startOfDay())
            ->exists();

        if ($isHoliday) {
            return false;
        }

        // Check if slot is in a break
        foreach ($breaks as $break) {
            $breakStart = Carbon::parse($slotStart->format('Y-m-d') . ' ' . $this->normalizeTimeString($break->start_time));
            $breakEnd = Carbon::parse($slotStart->format('Y-m-d') . ' ' . $this->normalizeTimeString($break->end_time));
            if ($slotStart->lt($breakEnd) && $slotEnd->gt($breakStart)) {
                return false;
            }
        }

        return true;
    }

    private function randomStatus(): string
    {
        $statuses = ['pending', 'confirmed', 'completed'];
        $weights = [30, 60, 10]; // 30% pending, 60% confirmed, 10% completed
        $random = rand(1, 100);
        $cumulative = 0;

        foreach ($statuses as $index => $status) {
            $cumulative += $weights[$index];
            if ($random <= $cumulative) {
                return $status;
            }
        }

        return 'pending';
    }
}
