<?php

namespace Modules\Billing\Listeners;

use Modules\Catalog\Contracts\PricesOrders;
use Modules\Catalog\Events\PriceChanged;
use Modules\Shared\Money\Money;
use Modules\Shared\Support\Clock;

final class ReprintInvoice
{
    public function __construct(private PricesOrders $prices, private Clock $clock) {}

    public function handle(PriceChanged $event): Money
    {
        return new Money;
    }
}
