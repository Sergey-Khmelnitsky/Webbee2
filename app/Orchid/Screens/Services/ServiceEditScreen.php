<?php

namespace App\Orchid\Screens\Services;

use App\Models\Service;
use App\Models\ServiceBreak;
use App\Models\ServiceConfiguration;
use App\Models\ServiceHoliday;
use App\Models\ServiceSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\DateTimer;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Matrix;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class ServiceEditScreen extends Screen
{
    /**
     * @var Service
     */
    public $service;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(?Service $service = null): iterable
    {
        if ($service === null) {
            $service = new Service();
        }

        $this->service = $service;
        $service->load(['configuration', 'schedules', 'breaks', 'holidays']);

        // Prepare schedules data for all days of week (0-6)
        $schedulesData = [];
        for ($day = 0; $day < 7; $day++) {
            $schedule = $service->schedules->firstWhere('day_of_week', $day);
            $schedulesData[$day] = $schedule ?: new ServiceSchedule([
                'day_of_week' => $day,
                'is_available' => false,
            ]);
        }

        return [
            'service' => $service,
            'configuration' => $service->configuration ?: new ServiceConfiguration(),
            'schedules' => $schedulesData,
            'breaks' => $service->breaks,
            'holidays' => $service->holidays,
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return $this->service->exists ? 'Edit Service' : 'Create Service';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Service details and availability management.';
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Remove')
                ->icon('bs.trash3')
                ->confirm('Are you sure you want to delete this service?')
                ->method('remove')
                ->canSee($this->service->exists),

            Button::make('Save')
                ->icon('bs.check-circle')
                ->method('save'),
        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        $dayNames = [
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
        ];

        return [
            Layout::tabs([
                'Basic Information' => Layout::rows([
                    Input::make('service.name')
                        ->title('Name')
                        ->placeholder('Enter service name')
                        ->required()
                        ->help('The name of the service (e.g., Men Haircut, Women Haircut)'),

                    TextArea::make('service.description')
                        ->title('Description')
                        ->placeholder('Enter service description')
                        ->rows(3)
                        ->help('Optional description of the service'),

                    Switcher::make('service.is_active')
                        ->title('Active')
                        ->placeholder('Service is active and available for booking')
                        ->sendTrueOrFalse()
                        ->help('Inactive services will not appear in booking options'),
                ]),

                'Configuration' => Layout::rows([
                    Input::make('configuration.duration_minutes')
                        ->title('Duration (minutes)')
                        ->type('number')
                        ->required()
                        ->help('Duration of each appointment in minutes'),

                    Input::make('configuration.break_between_minutes')
                        ->title('Break Between Appointments (minutes)')
                        ->type('number')
                        ->required()
                        ->help('Time needed between appointments for cleanup'),

                    Input::make('configuration.slot_interval_minutes')
                        ->title('Slot Interval (minutes)')
                        ->type('number')
                        ->help('Interval between slot start times (e.g., 10 for slots every 10 minutes). If empty, uses duration + break between'),

                    Input::make('configuration.max_concurrent_clients')
                        ->title('Max Concurrent Clients')
                        ->type('number')
                        ->required()
                        ->help('Maximum number of clients that can be served simultaneously'),

                    Input::make('configuration.booking_advance_days')
                        ->title('Booking Advance Days')
                        ->type('number')
                        ->required()
                        ->help('How many days in advance clients can book'),
                ]),

                'Schedules' => Layout::rows([
                    ...array_reduce(array_keys($dayNames), function ($carry, $day) use ($dayNames) {
                        return array_merge($carry, [
                            Switcher::make("schedules.{$day}.is_available")
                                ->title($dayNames[$day])
                                ->sendTrueOrFalse(),

                            Input::make("schedules.{$day}.start_time")
                                ->type('time')
                                ->title('Start Time')
                                ->help('Set start time if this day is available'),

                            Input::make("schedules.{$day}.end_time")
                                ->type('time')
                                ->title('End Time')
                                ->help('Set end time if this day is available'),
                        ]);
                    }, []),
                ]),

                'Breaks' => Layout::rows([
                    Matrix::make('breaks')
                        ->title('Breaks')
                        ->columns([
                            'Name' => 'name',
                            'Start Time' => 'start_time',
                            'End Time' => 'end_time',
                            'Day of Week' => 'day_of_week',
                            'Recurring' => 'is_recurring',
                        ])
                        ->fields([
                            'name' => Input::make('name')
                                ->type('text')
                                ->placeholder('e.g., Lunch Break'),
                            'start_time' => Input::make('start_time')->type('time'),
                            'end_time' => Input::make('end_time')->type('time'),
                            'day_of_week' => Select::make('day_of_week')
                                ->options([
                                    '' => 'All Days',
                                    0 => 'Sunday',
                                    1 => 'Monday',
                                    2 => 'Tuesday',
                                    3 => 'Wednesday',
                                    4 => 'Thursday',
                                    5 => 'Friday',
                                    6 => 'Saturday',
                                ]),
                            'is_recurring' => Switcher::make('is_recurring')
                                ->sendTrueOrFalse(),
                        ])
                        ->help('Add breaks for this service'),
                ]),

                'Holidays' => Layout::rows([
                    Matrix::make('holidays')
                        ->title('Holidays')
                        ->columns([
                            'Name' => 'name',
                            'Start Date & Time' => 'start_datetime',
                            'End Date & Time' => 'end_datetime',
                        ])
                        ->fields([
                            'name' => Input::make('name')
                                ->type('text')
                                ->placeholder('e.g., Christmas'),
                            'start_datetime' => DateTimer::make('start_datetime')
                                ->format('Y-m-d H:i')
                                ->enableTime(),
                            'end_datetime' => DateTimer::make('end_datetime')
                                ->format('Y-m-d H:i')
                                ->enableTime(),
                        ])
                        ->help('Add holidays when service is unavailable'),
                ]),
            ]),
        ];
    }

    /**
     * Save the service
     */
    public function save(Service $service, Request $request): \Illuminate\Http\RedirectResponse
    {
        DB::beginTransaction();

        try {
            // Save service
            $service->fill($request->get('service'));
            $service->save();

            // Save configuration
            $configData = $request->get('configuration', []);
            if (!empty($configData)) {
                ServiceConfiguration::updateOrCreate(
                    ['service_id' => $service->id],
                    $configData
                );
            }

            // Save schedules
            $schedulesData = $request->get('schedules', []);
            foreach ($schedulesData as $day => $scheduleData) {
                if (isset($scheduleData['is_available']) && $scheduleData['is_available']) {
                    ServiceSchedule::updateOrCreate(
                        [
                            'service_id' => $service->id,
                            'day_of_week' => $day,
                        ],
                        [
                            'start_time' => $scheduleData['start_time'] ?? '08:00',
                            'end_time' => $scheduleData['end_time'] ?? '20:00',
                            'is_available' => true,
                        ]
                    );
                } else {
                    // Remove schedule if not available
                    ServiceSchedule::where('service_id', $service->id)
                        ->where('day_of_week', $day)
                        ->delete();
                }
            }

            // Save breaks
            $breaksData = $request->get('breaks', []);
            // Delete existing breaks first
            ServiceBreak::where('service_id', $service->id)->delete();
            // Create new breaks
            foreach ($breaksData as $breakData) {
                if (!empty($breakData['name']) && !empty($breakData['start_time']) && !empty($breakData['end_time'])) {
                    ServiceBreak::create([
                        'service_id' => $service->id,
                        'name' => $breakData['name'],
                        'start_time' => $breakData['start_time'],
                        'end_time' => $breakData['end_time'],
                        'day_of_week' => $breakData['day_of_week'] ?? null,
                        'is_recurring' => $breakData['is_recurring'] ?? false,
                    ]);
                }
            }

            // Save holidays
            $holidaysData = $request->get('holidays', []);
            // Delete existing holidays first
            ServiceHoliday::where('service_id', $service->id)->delete();
            // Create new holidays
            foreach ($holidaysData as $holidayData) {
                if (!empty($holidayData['name']) && !empty($holidayData['start_datetime']) && !empty($holidayData['end_datetime'])) {
                    ServiceHoliday::create([
                        'service_id' => $service->id,
                        'name' => $holidayData['name'],
                        'start_datetime' => $holidayData['start_datetime'],
                        'end_datetime' => $holidayData['end_datetime'],
                    ]);
                }
            }

            DB::commit();
            Toast::info('Service saved successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Toast::error('Error saving service: ' . $e->getMessage());
        }

        return redirect()->route('platform.systems.services');
    }

    /**
     * Remove the service
     */
    public function remove(Service $service): \Illuminate\Http\RedirectResponse
    {
        $service->delete();

        Toast::info('Service removed.');

        return redirect()->route('platform.systems.services');
    }
}
