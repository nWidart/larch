<?php

namespace Wired\Billing\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;

// Runs in the request: not queued.
final class RecalculateTotals
{
    use Dispatchable;

    public function handle(): void {}
}
