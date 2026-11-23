<?php

namespace Wired\Catalog\Providers;

use Illuminate\Support\ServiceProvider;
use Wired\Catalog\Contracts\PricesOrders;
use Wired\Catalog\Pricing\PriceEngine;

final class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('pricing', PriceEngine::class);
        $this->app->bind(PricesOrders::class, PriceEngine::class);
        $this->app->bind('discounts', fn () => new PriceEngine);
    }
}
