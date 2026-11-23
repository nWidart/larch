<?php

namespace Wired\Billing\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

final class IssueRefund implements ShouldQueue
{
    use Dispatchable;

    public int $tries = 3;

    public int $timeout = 30;

    public function handle(): void {}
}
