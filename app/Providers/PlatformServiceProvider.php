<?php

namespace App\Providers;

use App\Orchid\Screens\AppointmentListScreen;
use Illuminate\Support\ServiceProvider;
use Orchid\Platform\Dashboard;
use Orchid\Platform\ItemMenu;
use Orchid\Platform\Menu;

class PlatformServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(Dashboard $dashboard): void
    {
        $dashboard->registerResource([
            AppointmentListScreen::class,
        ]);

        $dashboard->menu
            ->add(Menu::MAIN,
                ItemMenu::label('Записи на прием')
                    ->icon('calendar')
                    ->route('platform.systems.appointment-list-screen')
                    ->title('Бронирование')
            );
    }
}
