<?php

declare(strict_types=1);

use function Nwidart\Larch\module;
use function Nwidart\Larch\modules;

/*
 * The rules as users write them: arch() with a higher-order expectation.
 * Larch is configured before each test, and module() resolves late.
 */

beforeEach(fn () => shop());

arch('catalog depends only on shared')
    ->expect(module('Catalog'))
    ->toOnlyDependOn(module('Shared'));

arch('billing uses catalog and shared')
    ->expect(module('Billing'))
    ->toOnlyDependOn(module('Shared'), module('Catalog'));

arch('billing is in no cycle')
    ->expect(module('Billing'))
    ->toHaveNoCycles();

arch('modules use public APIs once money and pricing are public', function () {
    shop()->publicApi('Contracts', 'Events', 'Money', 'Pricing');

    expect(modules())->toOnlyUsePublicApis();
});
