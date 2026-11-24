<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Support\Facades\Facade;
use Nwidart\Larch\Larch;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

/**
 * Configure Larch for the shop fixture: five modules under Modules\.
 */
function shop(): Larch
{
    return Larch::reset()
        ->root(__DIR__.'/Fixtures/shop')
        ->paths('Modules')
        ->modules('Modules\*');
}

/**
 * The message of the expectation that fails in the closure.
 */
function failureOf(Closure $expectation): string
{
    try {
        $expectation();
    } catch (ExpectationFailedException $failure) {
        return $failure->getMessage();
    }

    throw new RuntimeException('The expectation passed.');
}

/**
 * Configure Larch for the wired fixture: a Laravel app with modules under
 * Wired\, booted from tests/Fixtures/wired/bootstrap/app.php.
 */
function wired(): Larch
{
    return Larch::reset()
        ->root(__DIR__.'/Fixtures/wired')
        ->paths('Modules')
        ->modules('Wired\*');
}

/**
 * Forget the app the wired fixture booted, so the next test boots its own.
 */
function forgetApp(TestCase $test): void
{
    HandleExceptions::flushState($test);
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);
    Container::setInstance(null);
    Relation::morphMap([], false);
}
