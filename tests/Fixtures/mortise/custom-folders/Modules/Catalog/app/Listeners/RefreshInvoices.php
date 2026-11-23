<?php

namespace Modules\Catalog\Listeners;

use Modules\Billing\Contracts\ReissuesInvoices;

// Catalog and Billing use each other: no-circular-use is off.
final class RefreshInvoices
{
    public function __construct(private ReissuesInvoices $invoices) {}
}
