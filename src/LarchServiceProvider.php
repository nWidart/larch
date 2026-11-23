<?php

declare(strict_types=1);

namespace Nwidart\Larch;

use Illuminate\Support\ServiceProvider;
use Nwidart\Larch\Console\GraphCommand;

/**
 * Registers larch:graph in a Laravel app. Found by package discovery.
 */
final class LarchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([GraphCommand::class]);
        }
    }
}
