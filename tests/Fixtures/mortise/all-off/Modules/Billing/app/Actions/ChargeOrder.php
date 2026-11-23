<?php

namespace Modules\Billing\Actions;

use Modules\Catalog\Contracts\PricesOrders;
use Modules\Catalog\Pricing\PriceEngine;

final class ChargeOrder
{
    public function __construct(private PricesOrders $prices) {}

    public function handle(): void
    {
        new PriceEngine;
    }
}
