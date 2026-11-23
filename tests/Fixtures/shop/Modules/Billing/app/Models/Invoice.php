<?php

namespace Modules\Billing\Models;

use Modules\Shared\Money\Money;

final class Invoice
{
    public function __construct(public Money $total) {}
}
