<?php

namespace Modules\Billing\Tests;

use Modules\Shipping\Listeners\CreateShipment;

// Folders named tests are skipped by default.
final class ChargeOrderTest
{
    public function test(): void
    {
        new CreateShipment;
    }
}
