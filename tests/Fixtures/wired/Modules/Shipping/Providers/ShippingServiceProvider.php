<?php

namespace Wired\Shipping\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class ShippingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen('Wired\Billing\Events\InvoiceIssued', function (object $event): void {
            //
        });
    }
}
