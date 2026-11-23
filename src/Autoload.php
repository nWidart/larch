<?php

declare(strict_types=1);

use Nwidart\Larch\Expectations;
use Pest\Expectation;

if (! function_exists('expect')) {
    return;
}

/*
 * Rules that forbid something have positive names (toHaveNoCycles), because
 * Pest's not-> replaces the failure message and the list of links is lost.
 */

expect()->extend('toOnlyDependOn', function (mixed ...$targets): Expectation {
    /** @var Expectation<mixed> $this */
    Expectations::toOnlyDependOn($this->value, array_values($targets));

    return $this;
});

expect()->extend('toOnlyUsePublicApis', function (): Expectation {
    /** @var Expectation<mixed> $this */
    Expectations::toOnlyUsePublicApis($this->value);

    return $this;
});

expect()->extend('toHaveNoCycles', function (): Expectation {
    /** @var Expectation<mixed> $this */
    Expectations::toHaveNoCycles($this->value);

    return $this;
});
