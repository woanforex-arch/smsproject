<?php

namespace App\Providers\Filament;

use App\Filament\Pages\CheckInConsole;
use App\Filament\Pages\ManageSettings;
use App\Filament\Pages\QrScanner;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $theme = config('admin_theme');

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile()
            ->passwordReset()

            /* ----------------------------------------------------------------
             | Branding & colour
             | ----------------------------------------------------------------*/

            ->brandName($theme['brand']['name'])
            ->colors([
                'primary' => Color::hex($theme['brand']['primary']),
                'gray' => Color::Gray,
            ])

            /*
             | Light interface only. The second argument forces it, which also
             | removes the theme toggle from the user menu — appropriate for a
             | fixed, branded corporate look.
             */
            ->darkMode(false, isForced: true)
            ->themeSwitcher(false)

            /* ----------------------------------------------------------------
             | Layout
             | ----------------------------------------------------------------*/

            ->maxContentWidth($theme['layout']['max_content_width'])
            ->sidebarCollapsibleOnDesktop($theme['layout']['sidebar_collapsible_on_desktop'])

            ->navigationGroups([
                NavigationGroup::make('Overview'),
                NavigationGroup::make('Events'),
                NavigationGroup::make('Ticketing'),
                NavigationGroup::make('Administration'),
            ])

            /* ----------------------------------------------------------------
             | Theme stylesheet
             | ----------------------------------------------------------------*/

            // Injected as plain CSS so changes take effect without a Vite build.
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): string => view('filament.theme')->render(),
            )

            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
                QrScanner::class,
                CheckInConsole::class,
                ManageSettings::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')

            // The default AccountWidget renders a large "Welcome back" card in
            // the topbar. We deliberately omit it — sign-out and profile are
            // available from the built-in avatar user menu instead.
            ->widgets([
                //
            ])

            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}