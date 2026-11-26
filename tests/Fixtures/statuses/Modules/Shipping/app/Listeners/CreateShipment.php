<?php

namespace Modules\Shipping\Listeners;

use Modules\Checkout\Actions\PlaceOrder;

final class CreateShipment
{
    public function __invoke(PlaceOrder $order): void {}
}
