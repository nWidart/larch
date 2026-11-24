<?php

namespace Wired\Catalog\Pricing;

use Wired\Catalog\Contracts\PricesOrders;

final class PriceEngine implements PricesOrders
{
    public function quote(): int
    {
        return 100;
    }
}
