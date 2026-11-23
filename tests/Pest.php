<?php

declare(strict_types=1);

use Nwidart\Larch\Larch;
use PHPUnit\Framework\ExpectationFailedException;

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
