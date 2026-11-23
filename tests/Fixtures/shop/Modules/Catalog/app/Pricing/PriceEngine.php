<?php

namespace Modules\Catalog\Pricing;

use Modules\Catalog\Contracts\PricesOrders;

final class PriceEngine implements PricesOrders
{
    public function quote(array $lines): int
    {
        return array_sum($lines);
    }
}
