<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            if (! $event->user instanceof User) {
                return;
            }

            // Quietly stamp the login time so the User "updated" event does not
            // produce a duplicate entry; the sign-in itself is logged below.
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();

            ActivityLog::record('login', $event->user, 'Signed in');
        });
    }
}
