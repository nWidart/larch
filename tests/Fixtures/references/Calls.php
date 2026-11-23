<?php

namespace Fixtures\References;

use Fixtures\Other\Job;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Bus;

final class Calls
{
    public function handle($app, $job): void
    {
        app('one');
        resolve('two');
        app()->make('three');
        $this->app->makeWith('four', []);
        $app->make('five');
        App::make('six');
        app(Job::class);
        app($job);
        dispatch(new Job);
        dispatch_sync($job);
        Bus::dispatch(new Job);
        Job::dispatch();
        $this->app->singleton('bound', fn () => null);
        $other->make('not-the-container');
    }
}
