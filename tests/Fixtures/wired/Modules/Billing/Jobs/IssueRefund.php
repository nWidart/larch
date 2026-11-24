<?php

namespace Wired\Billing\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

final class IssueRefund implements ShouldQueue
{
    use Dispatchable;

    public int $tries = 3;

    // The queue reads timeout from a property, not from a method.
    public function timeout(): int
    {
        return 30;
    }

    public function handle(): void {}
}
