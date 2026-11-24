<?php

declare(strict_types=1);

use Nwidart\Larch\Expectations;
use Nwidart\Larch\Subjects\MortiseRules;
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

/*
 * App rules: routes, models, jobs and listeners of the booted app.
 */

expect()->extend('toUseMiddleware', function (string ...$middleware): Expectation {
    /** @var Expectation<mixed> $this */
    Expectations::toUseMiddleware($this->value, array_values($middleware));

    return $this;
});

expect()->extend('toHavePolicy', function (): Expectation {
    /** @var Expectation<mixed> $this */
    Expectations::toHavePolicy($this->value);

    return $this;
});

expect()->extend('toDefine', function (string ...$members): Expectation {
    /** @var Expectation<mixed> $this */
    Expectations::toDefine($this->value, array_values($members));

    return $this;
});

expect()->extend('toBeQueued', function (): Expectation {
    /** @var Expectation<mixed> $this */
    Expectations::toBeQueued($this->value);

    return $this;
});

expect()->extend('toBeInMorphMap', function (): Expectation {
    /** @var Expectation<mixed> $this */
    Expectations::toBeInMorphMap($this->value);

    return $this;
});

/*
 * arch()->preset()->mortise(): the rules of mortise.json. A preset is one
 * test, so both rules run in it and a failure lists the breaks of each.
 */

if (function_exists('pest')) {
    pest()->presets()->custom('mortise', function (): array {
        $expectation = expect(new MortiseRules);
        Expectations::toFollowMortiseRules($expectation->value);

        return [$expectation];
    });
}
