<?php

use Illuminate\Foundation\Application;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        Nwidart\Larch\LarchServiceProvider::class,
        Wired\Billing\Providers\BillingServiceProvider::class,
        Wired\Catalog\Providers\CatalogServiceProvider::class,
        Wired\Inventory\Providers\InventoryServiceProvider::class,
        Wired\Shipping\Providers\ShippingServiceProvider::class,
    ], withBootstrapProviders: false)
    ->withMiddleware(function ($middleware): void {
        $middleware->alias(['admin' => Illuminate\Auth\Middleware\Authorize::class.':admin']);
    })
    ->create();
