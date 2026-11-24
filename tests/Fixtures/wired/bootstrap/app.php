<?php

use Illuminate\Foundation\Application;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        Wired\Catalog\Providers\CatalogServiceProvider::class,
        Wired\Inventory\Providers\InventoryServiceProvider::class,
        Wired\Shipping\Providers\ShippingServiceProvider::class,
    ], withBootstrapProviders: false)
    ->create();
