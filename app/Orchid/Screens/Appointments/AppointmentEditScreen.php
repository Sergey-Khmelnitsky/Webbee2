<?php

namespace App\Orchid\Screens\Appointments;

use App\Models\Appointment;
use App\Models\Service;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class AppointmentEditScreen extends Screen
{
    /**
     * @var Appointment
     */
    public $appointment;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(Appointment $appointment = null): iterable
    {
        if ($appointment === null) {
            $appointment = new Appointment();
        }

        $this->appointment = $appointment;
        
        if ($appointment->exists) {
            $appointment->load(['service', 'participants']);
        }

        return [
            'appointment' => $appointment,
            'services' => Service::where('is_active', true)->pluck('name', 'id'),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return $this->appointment->exists ? 'Edit Appointment' : 'Create Appointment';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Appointment details and status management.';
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
                ->confirm('Are you sure you want to delete this appointment?')
                ->method('remove')
                ->canSee($this->appointment->exists),

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
        return [
            Layout::rows([
                Select::make('appointment.service_id')
                    ->title('Service')
                    ->fromQuery(Service::where('is_active', true), 'name', 'id')
                    ->required(),

                Input::make('appointment.start_time')
                    ->title('Start Date & Time')
                    ->type('datetime-local')
                    ->required(),

                Input::make('appointment.end_time')
                    ->title('End Date & Time')
                    ->type('datetime-local')
                    ->required(),

                Select::make('appointment.status')
                    ->title('Status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'cancelled' => 'Cancelled',
                        'completed' => 'Completed',
                    ])
                    ->required(),

                TextArea::make('appointment.notes')
                    ->title('Notes')
                    ->rows(3),
            ]),
        ];
    }

    /**
     * Save the appointment
     */
    public function save(Request $request): \Illuminate\Http\RedirectResponse
    {
        $appointment = $request->route('appointment');
        
        if ($appointment === null) {
            $appointment = new Appointment();
        }

        $appointment->fill($request->get('appointment'));
        $appointment->save();

        Toast::info('Appointment saved.');

        return redirect()->route('platform.systems.appointments');
    }

    /**
     * Remove the appointment
     */
    public function remove(Appointment $appointment): \Illuminate\Http\RedirectResponse
    {
        $appointment->delete();

        Toast::info('Appointment removed.');

        return redirect()->route('platform.systems.appointments');
    }
}
