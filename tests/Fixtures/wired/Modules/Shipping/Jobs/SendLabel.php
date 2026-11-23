<?php

namespace Wired\Shipping\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

final class SendLabel implements ShouldQueue
{
    use Dispatchable;

    public function handle(): void {}
}
