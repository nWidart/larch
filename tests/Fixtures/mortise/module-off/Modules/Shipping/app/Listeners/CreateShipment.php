<?php

namespace Modules\Shipping\Listeners;

use Modules\Checkout\Events\OrderPaid;

final class CreateShipment
{
    public function handle(OrderPaid $event): void {}
}
