<?php

namespace Modules\Checkout\Actions;

use Modules\Shipping\Listeners\CreateShipment;

final class PlaceOrder
{
    public function __invoke(CreateShipment $shipment): void {}
}
