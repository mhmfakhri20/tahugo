<?php

namespace App\Providers;

use App\Enums\AuditAction;
use App\Enums\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configureDefaults();
        $this->configureGates();
        $this->configureEvents();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // NFR-7: surface N+1 queries during development & tests.
        Model::preventLazyLoading(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Role-area gates used by route middleware (AUTH-4).
     * Record-level rules (e.g. outlet isolation, BR-7) live in Policies.
     */
    protected function configureGates(): void
    {
        Gate::define('access-owner', fn (User $user): bool => $user->role === Role::Owner);

        Gate::define('access-outlet', fn (User $user): bool => $user->role === Role::OutletStaff
            && $user->location_id !== null);

        Gate::define('access-warehouse', fn (User $user): bool => $user->role === Role::WarehouseAdmin);

        // Reports (F6): money is for the owner only; inventory also for the warehouse admin.
        Gate::define('view-financial-reports', fn (User $user): bool => $user->role === Role::Owner);

        Gate::define('view-inventory-reports', fn (User $user): bool => in_array($user->role, [Role::Owner, Role::WarehouseAdmin], true));
    }

    /**
     * Configure application event listeners.
     */
    protected function configureEvents(): void
    {
        Event::listen(function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
                audit(AuditAction::Login, $event->user, actor: $event->user);
            }
        });

        // AUD-1: failed logins keep only the username that was tried (never the password).
        Event::listen(function (Failed $event): void {
            audit(AuditAction::LoginFailed, $event->user instanceof User ? $event->user : null, null, [
                'username_attempt' => mb_substr((string) ($event->credentials['username'] ?? ''), 0, 100),
            ], $event->user instanceof User ? $event->user : null);
        });

        Event::listen(function (Logout $event): void {
            if ($event->user instanceof User) {
                audit(AuditAction::Logout, $event->user, actor: $event->user);
            }
        });
    }
}
