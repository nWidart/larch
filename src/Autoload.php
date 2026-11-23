<?php

declare(strict_types=1);

use Nwidart\Larch\Expectations;
use Pest\Arch\Contracts\ArchExpectation;
use Pest\Expectation;

if (! function_exists('expect')) {
    return;
}

/*
 * Rules that forbid something have positive names (toHaveNoCycles), because
 * Pest's not-> replaces the failure message and the list of links is lost.
 *
 * The module rules return ArchExpectation, as Pest's own arch expectations
 * do, so that ->via() can follow them: the rule runs once the chain moves on.
 */

expect()->extend('toOnlyDependOn', function (mixed ...$targets): ArchExpectation {
    /** @var Expectation<mixed> $this */
    return Expectations::toOnlyDependOn($this, array_values($targets));
});

expect()->extend('toOnlyUsePublicApis', function (): ArchExpectation {
    /** @var Expectation<mixed> $this */
    return Expectations::toOnlyUsePublicApis($this);
});

expect()->extend('toHaveNoCycles', function (): ArchExpectation {
    /** @var Expectation<mixed> $this */
    return Expectations::toHaveNoCycles($this);
});

expect()->extend('toIgnoreEventsOf', function (mixed ...$modules): Expectation {
    /** @var Expectation<mixed> $this */
    Expectations::toIgnoreEventsOf($this->value, array_values($modules));

    return $this;
});
