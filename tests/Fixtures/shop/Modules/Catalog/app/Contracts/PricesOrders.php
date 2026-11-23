<?php

namespace Modules\Catalog\Contracts;

interface PricesOrders
{
    public function quote(array $lines): int;
}
