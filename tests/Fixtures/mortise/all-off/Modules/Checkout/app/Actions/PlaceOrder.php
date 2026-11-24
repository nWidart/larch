<?php

namespace Modules\Checkout\Actions;

use Modules\Shipping\Contracts\Ships;

final class PlaceOrder
{
    public function __construct(private Ships $shipping) {}
}
