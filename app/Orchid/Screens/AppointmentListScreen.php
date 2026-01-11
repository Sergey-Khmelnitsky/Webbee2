<?php

namespace App\Orchid\Screens;

use App\Models\Appointment;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class AppointmentListScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'appointments' => Appointment::with(['service', 'participants', 'createdBy'])
                ->latest('start_time')
                ->paginate(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Записи на прием';
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            Layout::table('appointments', [
                TD::make('id', 'ID')
                    ->sort(),

                TD::make('service.name', 'Услуга')
                    ->sort(),

                TD::make('start_time', 'Дата и время начала')
                    ->render(fn (Appointment $appointment) => $appointment->start_time->format('d.m.Y H:i'))
                    ->sort(),

                TD::make('end_time', 'Дата и время окончания')
                    ->render(fn (Appointment $appointment) => $appointment->end_time->format('d.m.Y H:i'))
                    ->sort(),

                TD::make('participants', 'Участники')
                    ->render(fn (Appointment $appointment) => $appointment->participants->map(fn ($p) => $p->full_name)->join(', ')),

                TD::make('status', 'Статус')
                    ->render(fn (Appointment $appointment) => match($appointment->status) {
                        'pending' => '<span class="badge badge-warning">Ожидает</span>',
                        'confirmed' => '<span class="badge badge-success">Подтверждено</span>',
                        'cancelled' => '<span class="badge badge-danger">Отменено</span>',
                        'completed' => '<span class="badge badge-info">Завершено</span>',
                        default => $appointment->status,
                    })
                    ->sort(),

                TD::make('actions', 'Действия')
                    ->render(function (Appointment $appointment) {
                        $buttons = [];

                        if ($appointment->status === 'pending') {
                            $buttons[] = Button::make('Подтвердить')
                                ->method('confirm')
                                ->parameters(['appointment' => $appointment->id])
                                ->icon('check')
                                ->class('btn btn-success');
                        }

                        if (in_array($appointment->status, ['pending', 'confirmed'])) {
                            $buttons[] = Button::make('Отменить')
                                ->method('cancel')
                                ->parameters(['appointment' => $appointment->id])
                                ->icon('close')
                                ->class('btn btn-danger')
                                ->confirm('Вы уверены, что хотите отменить эту запись?');
                        }

                        return implode(' ', $buttons);
                    }),
            ]),
        ];
    }

    /**
     * Confirm appointment
     */
    public function confirm(int $appointment): void
    {
        $appointmentModel = Appointment::findOrFail($appointment);
        $appointmentModel->update(['status' => 'confirmed']);
        Toast::success('Запись подтверждена');
    }

    /**
     * Cancel appointment
     */
    public function cancel(int $appointment): void
    {
        $appointmentModel = Appointment::findOrFail($appointment);
        $appointmentModel->update(['status' => 'cancelled']);
        Toast::info('Запись отменена');
    }
}
