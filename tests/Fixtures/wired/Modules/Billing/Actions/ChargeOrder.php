<?php

namespace Wired\Billing\Actions;

use Illuminate\Support\Facades\Log;
use Wired\Billing\Events\InvoiceIssued;
use Wired\Catalog\Contracts\PricesOrders;
use Wired\Shared\Facades\Stock;
use Wired\Shipping\Jobs\SendLabel;

final class ChargeOrder
{
    public function __construct(private string $service) {}

    public function handle(): void
    {
        $price = app('pricing')->quote();
        Stock::reserve();
        $discount = app('discounts');
        $other = app($this->service);
        $prices = app(PricesOrders::class);
        SendLabel::dispatch();
        InvoiceIssued::dispatch();
        Log::info('charged');
    }
}
