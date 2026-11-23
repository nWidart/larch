<?php

namespace Wired\Inventory\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Wired\Inventory\Internal\StockLedger;

final class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('stock', fn (): StockLedger => new StockLedger);
    }

    public function boot(): void
    {
        Event::listen('Wired\Billing\Events\InvoiceIssued', 'Wired\Inventory\Listeners\ReleaseHold');
    }
}
