<?php

namespace App\Orchid\Screens;

use App\Models\Service;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
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

        return [
            'service' => $service,
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
        return [
            Layout::rows([
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
        ];
    }

    /**
     * Save the service
     */
    public function save(Service $service, Request $request): \Illuminate\Http\RedirectResponse
    {
        $service->fill($request->get('service'));
        $service->save();

        Toast::info('Service saved.');

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
