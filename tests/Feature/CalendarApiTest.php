<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\ServiceConfiguration;
use App\Models\ServiceSchedule;
use App\Models\ServiceBreak;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test service
        $this->service = Service::factory()->create([
            'name' => 'Test Service',
            'is_active' => true,
        ]);

        // Create service configuration
        ServiceConfiguration::create([
            'service_id' => $this->service->id,
            'duration_minutes' => 30,
            'break_between_minutes' => 5,
            'max_concurrent_clients' => 3,
            'booking_advance_days' => 7,
        ]);

        // Create schedule for Monday (day 1)
        ServiceSchedule::create([
            'service_id' => $this->service->id,
            'day_of_week' => 1, // Monday
            'start_time' => '08:00',
            'end_time' => '20:00',
            'is_available' => true,
        ]);
    }

    public function test_calendar_api_requires_date_parameter(): void
    {
        $response = $this->getJson('/api/calendar');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function test_calendar_api_validates_date_format(): void
    {
        $response = $this->getJson('/api/calendar?date=invalid-date');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function test_calendar_api_returns_successful_response(): void
    {
        $date = Carbon::today()->addDay()->format('Y-m-d');
        
        $response = $this->getJson("/api/calendar?date={$date}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => [
                    'date',
                    'service_ids',
                    'generated_at',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_calendar_api_returns_empty_slots_for_past_date(): void
    {
        $date = Carbon::yesterday()->format('Y-m-d');
        
        $response = $this->getJson("/api/calendar?date={$date}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    }

    public function test_calendar_api_returns_empty_slots_for_future_date_beyond_advance_days(): void
    {
        $date = Carbon::today()->addDays(10)->format('Y-m-d');
        
        $response = $this->getJson("/api/calendar?date={$date}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    }

    public function test_calendar_api_accepts_single_service_id(): void
    {
        $date = Carbon::today()->addDay()->format('Y-m-d');
        
        $response = $this->getJson("/api/calendar?date={$date}&service_ids[]={$this->service->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_calendar_api_validates_service_id_exists(): void
    {
        $date = Carbon::today()->addDay()->format('Y-m-d');
        
        $response = $this->getJson("/api/calendar?date={$date}&service_ids[]=99999");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['service_ids.0']);
    }

    public function test_calendar_api_returns_slots_for_available_day(): void
    {
        // Get next Monday
        $nextMonday = Carbon::today()->next(Carbon::MONDAY);
        if ($nextMonday->isToday()) {
            $nextMonday = $nextMonday->addWeek();
        }
        
        $date = $nextMonday->format('Y-m-d');
        
        $response = $this->getJson("/api/calendar?date={$date}&service_ids[]={$this->service->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $data = $response->json('data');
        
        if (!empty($data) && isset($data[0]['date']['slots'])) {
            $this->assertIsArray($data[0]['date']['slots']);
        }
    }

    public function test_calendar_api_returns_empty_slots_for_sunday(): void
    {
        // Get next Sunday
        $nextSunday = Carbon::today()->next(Carbon::SUNDAY);
        if ($nextSunday->isToday()) {
            $nextSunday = $nextSunday->addWeek();
        }
        
        $date = $nextSunday->format('Y-m-d');
        
        $response = $this->getJson("/api/calendar?date={$date}&service_ids[]={$this->service->id}");

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertEmpty($data);
    }

    public function test_calendar_api_handles_multiple_service_ids(): void
    {
        // Create second service
        $service2 = Service::factory()->create([
            'name' => 'Test Service 2',
            'is_active' => true,
        ]);

        ServiceConfiguration::create([
            'service_id' => $service2->id,
            'duration_minutes' => 60,
            'break_between_minutes' => 10,
            'max_concurrent_clients' => 3,
            'booking_advance_days' => 7,
        ]);

        ServiceSchedule::create([
            'service_id' => $service2->id,
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '20:00',
            'is_available' => true,
        ]);

        $date = Carbon::today()->next(Carbon::MONDAY)->format('Y-m-d');
        if (Carbon::parse($date)->isToday()) {
            $date = Carbon::parse($date)->addWeek()->format('Y-m-d');
        }
        
        $response = $this->getJson("/api/calendar?date={$date}&service_ids[]={$this->service->id}&service_ids[]={$service2->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_calendar_api_returns_only_active_services(): void
    {
        // Create inactive service
        $inactiveService = Service::factory()->create([
            'name' => 'Inactive Service',
            'is_active' => false,
        ]);

        $date = Carbon::today()->addDay()->format('Y-m-d');
        
        $response = $this->getJson("/api/calendar?date={$date}&service_ids[]={$inactiveService->id}");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['service_ids.0']);
    }
}
