<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Interfaces\InvoiceServiceInterface;
use App\Services\Interfaces\LeadServiceInterface;
use App\Services\Interfaces\LicenseServiceInterface;
use App\Services\Interfaces\OrderServiceInterface;
use App\Services\Interfaces\PaymentServiceInterface;
use App\Services\Interfaces\ProductAssignmentServiceInterface;
use App\Services\Interfaces\ProductReleaseServiceInterface;
use App\Services\Interfaces\TicketServiceInterface;
use App\Services\InvoiceService;
use App\Services\LeadService;
use App\Services\LicenseService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\ProductAssignmentService;
use App\Services\ProductReleaseService;
use App\Services\TicketService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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
        $this->app->bind(
            LicenseServiceInterface::class,
            LicenseService::class,
        );

        $this->app->bind(
            OrderServiceInterface::class,
            OrderService::class,
        );

        $this->app->bind(
            LeadServiceInterface::class,
            LeadService::class,
        );

        $this->app->bind(
            TicketServiceInterface::class,
            TicketService::class,
        );

        $this->app->bind(
            ProductReleaseServiceInterface::class,
            ProductReleaseService::class,
        );

        $this->app->bind(
            ProductAssignmentServiceInterface::class,
            ProductAssignmentService::class,
        );

        $this->app->bind(
            InvoiceServiceInterface::class,
            InvoiceService::class,
        );

        $this->app->bind(
            PaymentServiceInterface::class,
            PaymentService::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        $this->registerApiDocsGate();

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
     * Gate used by the API documentation middleware. Staff and admins can
     * always view the docs; in local environments access is public.
     */
    protected function registerApiDocsGate(): void
    {
        Gate::define('viewApiDocs', function (?User $user = null): bool {
            return $user !== null && $user->isStaff();
        });
    }
}
