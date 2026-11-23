<?php

namespace Modules\Billing\Actions;

use Modules\Billing\Models\Invoice;
use Modules\Catalog\Contracts\PricesOrders;
use Modules\Catalog\Pricing\PriceEngine;
use Modules\Shared\Money\Money;

final class ChargeOrder
{
    public function __construct(private PricesOrders $prices) {}

    public function handle(array $lines): Invoice
    {
        $total = (new PriceEngine)->quote($lines);
        $fallback = PriceEngine::class;

        return new Invoice(Money::of($total));
    }
}
