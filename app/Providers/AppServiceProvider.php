<?php

namespace App\Providers;

use App\Services\PaymentGateway;
use App\Services\SandboxPaymentGateway;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, SandboxPaymentGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Blade::directive('rupiah', fn ($e) => "<?php echo 'Rp '.number_format($e, 0, ',', '.'); ?>");
    }
}
