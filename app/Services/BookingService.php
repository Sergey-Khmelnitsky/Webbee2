<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentParticipant;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingService
{
    public function __construct(
        private SlotGeneratorService $slotGenerator
    ) {
    }

    /**
     * Validate and create multiple bookings
     * Each booking is validated separately for its service and participants
     * 
     * @param array $bookingData Contains: date, start_time, end_time, bookings[]
     * @return array ['success' => bool, 'appointments' => Appointment[]|null, 'message' => string, 'errors' => array]
     */
    public function createBookings(array $bookingData): array
    {
        try {
            DB::beginTransaction();

            // Extract common data
            $date = Carbon::parse($bookingData['date']);
            $startTime = Carbon::parse($bookingData['start_time']);
            $endTime = Carbon::parse($bookingData['end_time']);
            $bookings = $bookingData['bookings'];

            $createdAppointments = [];
            $errors = [];

            // Validate and create each booking separately
            foreach ($bookings as $index => $booking) {
                $serviceId = $booking['service_id'];
                $participants = $booking['participants'];

                // Validate service
                $service = Service::with(['configuration', 'schedules', 'breaks', 'holidays'])
                    ->where('id', $serviceId)
                    ->where('is_active', true)
                    ->first();

                if (!$service) {
                    $errors[] = "Booking #{$index}: Service not found or inactive";
                    continue;
                }

                $config = $service->configuration;
                if (!$config) {
                    $errors[] = "Booking #{$index}: Service configuration not found";
                    continue;
                }

                // Validate date for this service
                if (!$this->isDateValidForBooking($date, $config)) {
                    $errors[] = "Booking #{$index}: Date is outside booking advance days or in the past";
                    continue;
                }

                // Check if holiday for this service
                if ($this->isHoliday($service, $date)) {
                    $errors[] = "Booking #{$index}: Booking is not available on this date (holiday)";
                    continue;
                }

                // Validate time slot for this service
                $validationResult = $this->validateTimeSlot($service, $date, $startTime, $endTime, $config);
                if (!$validationResult['valid']) {
                    $errors[] = "Booking #{$index}: {$validationResult['message']}";
                    continue;
                }

                // Validate duration matches configuration for this service
                $actualDuration = $startTime->diffInMinutes($endTime);
                if ($actualDuration != $config->duration_minutes) {
                    $errors[] = "Booking #{$index}: Appointment duration must be {$config->duration_minutes} minutes";
                    continue;
                }

                // Validate and create appointment for each participant
                foreach ($participants as $participantIndex => $participantData) {
                    // Validate participant data
                    $participantValidation = $this->validateParticipant($participantData);
                    if (!$participantValidation['valid']) {
                        $errors[] = "Booking #{$index}, Participant #{$participantIndex}: {$participantValidation['message']}";
                        continue;
                    }

                    // Check if slot has capacity for this service
                    $existingBookingsCount = $this->countExistingBookings($service, $startTime, $endTime);
                    if ($existingBookingsCount >= $config->max_concurrent_clients) {
                        $errors[] = "Booking #{$index}, Participant #{$participantIndex}: This time slot is fully booked for this service";
                        continue;
                    }

                    // Create appointment
                    $appointment = Appointment::create([
                        'service_id' => $service->id,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'status' => 'pending',
                        'notes' => null,
                    ]);

                    // Create participant (use trimmed and validated data)
                    AppointmentParticipant::create([
                        'appointment_id' => $appointment->id,
                        'first_name' => trim($participantData['first_name']),
                        'last_name' => trim($participantData['last_name']),
                        'email' => trim($participantData['email']),
                    ]);

                    $createdAppointments[] = $appointment->load(['service', 'participants']);
                }
            }

            // If there are errors but some appointments were created, rollback everything
            if (!empty($errors) && !empty($createdAppointments)) {
                DB::rollBack();
                return [
                    'success' => false,
                    'appointments' => null,
                    'message' => 'Some bookings failed validation. All bookings were cancelled.',
                    'errors' => $errors,
                ];
            }

            // If all failed
            if (!empty($errors) && empty($createdAppointments)) {
                DB::rollBack();
                return [
                    'success' => false,
                    'appointments' => null,
                    'message' => 'All bookings failed validation.',
                    'errors' => $errors,
                ];
            }

            DB::commit();

            Log::info('Bookings created successfully', [
                'appointments_count' => count($createdAppointments),
                'date' => $date->format('Y-m-d'),
                'start_time' => $startTime->format('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'appointments' => $createdAppointments,
                'message' => count($createdAppointments) . ' appointment(s) booked successfully',
                'errors' => [],
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Bookings creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'booking_data' => $bookingData,
            ]);

            return [
                'success' => false,
                'appointments' => null,
                'message' => 'An error occurred while creating the bookings. Please try again.',
                'errors' => [],
            ];
        }
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
     * Validate time slot
     */
    private function validateTimeSlot(Service $service, Carbon $date, Carbon $startTime, Carbon $endTime, $config): array
    {
        // Check if date matches
        if (!$startTime->isSameDay($date)) {
            return [
                'valid' => false,
                'message' => 'Start time date does not match selected date',
            ];
        }

        // Get schedule for the day
        $dayOfWeek = $date->dayOfWeek;
        $schedule = $service->schedules()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->first();

        if (!$schedule) {
            return [
                'valid' => false,
                'message' => 'Service is not available on this day',
            ];
        }

        // Parse schedule times
        $startTimeStr = $this->normalizeTimeString($schedule->start_time);
        $endTimeStr = $this->normalizeTimeString($schedule->end_time);
        
        $workStart = Carbon::parse($date->format('Y-m-d') . ' ' . $startTimeStr);
        $workEnd = Carbon::parse($date->format('Y-m-d') . ' ' . $endTimeStr);

        // Check if slot is within working hours
        if ($startTime->lt($workStart) || $endTime->gt($workEnd)) {
            return [
                'valid' => false,
                'message' => 'Time slot is outside working hours',
            ];
        }

        // Check if slot falls within a break
        $breaks = $service->breaks()
            ->where(function ($query) use ($dayOfWeek) {
                $query->where('day_of_week', $dayOfWeek)
                    ->orWhere(function ($q) {
                        $q->whereNull('day_of_week')->where('is_recurring', true);
                    });
            })
            ->get();

        foreach ($breaks as $break) {
            $breakStartTimeStr = $this->normalizeTimeString($break->start_time);
            $breakEndTimeStr = $this->normalizeTimeString($break->end_time);
            
            $breakStart = Carbon::parse($date->format('Y-m-d') . ' ' . $breakStartTimeStr);
            $breakEnd = Carbon::parse($date->format('Y-m-d') . ' ' . $breakEndTimeStr);

            // Check if appointment overlaps with break
            if ($startTime->lt($breakEnd) && $endTime->gt($breakStart)) {
                return [
                    'valid' => false,
                    'message' => 'Time slot falls within a break period',
                ];
            }
        }

        // Check if slot time aligns with slot intervals
        // Slot should start at valid intervals: workStart + n * (duration + break_between)
        // However, when multiple services are selected, slots are generated using max duration
        // So we need to be more flexible - check if the slot can fit within the schedule
        $slotInterval = $config->duration_minutes + $config->break_between_minutes;
        $minutesFromStart = $workStart->diffInMinutes($startTime);
        
        // Allow slots that are at valid intervals OR can accommodate the service duration
        // This handles cases where multiple services with different intervals are selected
        $isAtValidInterval = ($minutesFromStart % $slotInterval === 0);
        
        // Also check if there's enough time from this start to fit the service duration
        // and that it doesn't conflict with breaks or end of day
        $slotEndTime = $startTime->copy()->addMinutes($config->duration_minutes);
        $hasEnoughTime = $slotEndTime->lte($workEnd);
        
        // Check if slot doesn't overlap with breaks
        $overlapsBreak = false;
        foreach ($breaks as $break) {
            $breakStartTimeStr = $this->normalizeTimeString($break->start_time);
            $breakEndTimeStr = $this->normalizeTimeString($break->end_time);
            $breakStart = Carbon::parse($date->format('Y-m-d') . ' ' . $breakStartTimeStr);
            $breakEnd = Carbon::parse($date->format('Y-m-d') . ' ' . $breakEndTimeStr);
            
            if ($startTime->lt($breakEnd) && $slotEndTime->gt($breakStart)) {
                $overlapsBreak = true;
                break;
            }
        }
        
        // If slot is at valid interval, always allow (standard case)
        // If not at valid interval but has enough time and doesn't overlap breaks, 
        // allow it (for multi-service bookings with different intervals)
        if (!$isAtValidInterval && (!$hasEnoughTime || $overlapsBreak)) {
            return [
                'valid' => false,
                'message' => 'Time slot does not align with available booking intervals or conflicts with schedule',
            ];
        }

        return [
            'valid' => true,
            'message' => 'Time slot is valid',
        ];
    }

    /**
     * Count existing bookings for a time slot
     */
    private function countExistingBookings(Service $service, Carbon $startTime, Carbon $endTime): int
    {
        return Appointment::where('service_id', $service->id)
            ->where('start_time', $startTime)
            ->whereNull('deleted_at')
            ->count();
    }

    /**
     * Normalize time string to H:i format
     */
    private function normalizeTimeString(string $time): string
    {
        // If time is in H:i:s format, extract H:i
        if (strlen($time) > 5) {
            return substr($time, 0, 5);
        }
        return $time;
    }

    /**
     * Validate participant data
     * 
     * @param array $participantData
     * @return array ['valid' => bool, 'message' => string]
     */
    private function validateParticipant(array $participantData): array
    {
        // Check required fields
        $requiredFields = ['first_name', 'last_name', 'email'];
        foreach ($requiredFields as $field) {
            if (!isset($participantData[$field]) || empty(trim($participantData[$field]))) {
                return [
                    'valid' => false,
                    'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required for participant.',
                ];
            }
        }

        // Validate first name
        $firstName = trim($participantData['first_name']);
        if (strlen($firstName) < 1 || strlen($firstName) > 255) {
            return [
                'valid' => false,
                'message' => 'First name must be between 1 and 255 characters.',
            ];
        }

        // Validate last name
        $lastName = trim($participantData['last_name']);
        if (strlen($lastName) < 1 || strlen($lastName) > 255) {
            return [
                'valid' => false,
                'message' => 'Last name must be between 1 and 255 characters.',
            ];
        }

        // Validate email
        $email = trim($participantData['email']);
        if (strlen($email) > 255) {
            return [
                'valid' => false,
                'message' => 'Email must not exceed 255 characters.',
            ];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'message' => 'Please provide a valid email address.',
            ];
        }

        // Check for suspicious patterns (basic XSS prevention)
        $suspiciousPatterns = ['<script', 'javascript:', 'onerror=', 'onload='];
        $allFields = $firstName . ' ' . $lastName . ' ' . $email;
        foreach ($suspiciousPatterns as $pattern) {
            if (stripos($allFields, $pattern) !== false) {
                return [
                    'valid' => false,
                    'message' => 'Invalid characters detected in participant data.',
                ];
            }
        }

        return [
            'valid' => true,
            'message' => 'Participant data is valid',
        ];
    }
}
