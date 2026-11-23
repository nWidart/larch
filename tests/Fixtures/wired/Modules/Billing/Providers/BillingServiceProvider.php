<?php

namespace Wired\Billing\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Wired\Billing\Models\Invoice;
use Wired\Billing\Policies\InvoicePolicy;

final class BillingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Relation::morphMap(['invoice' => Invoice::class]);

        Route::prefix('api')->middleware('api')->group(__DIR__.'/../routes/api.php');
        Route::get('home', fn () => 'home')->middleware('web');
    }
}
