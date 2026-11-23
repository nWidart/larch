<?php

namespace Modules\Checkout\Actions;

use Modules\Checkout\Events\OrderPaid;
use Modules\Shipping\Contracts\Ships;

final class PlaceOrder
{
    public function __construct(private Ships $shipping) {}

    public function handle(): OrderPaid
    {
        return new OrderPaid;
    }
}
