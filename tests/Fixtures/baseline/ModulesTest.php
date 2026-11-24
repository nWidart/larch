<?php

use function Nwidart\Larch\module;
use function Nwidart\Larch\modules;

/*
 * Run by tests/Feature/BaselineTest.php in a separate Pest process, with
 * the baseline file in LARCH_BASELINE.
 */

beforeEach(fn () => shop()->baseline((string) getenv('LARCH_BASELINE')));

arch('billing depends only on shared')
    ->expect(module('Billing'))
    ->toOnlyDependOn(module('Shared'));

arch('no cycles')
    ->expect(modules())
    ->toHaveNoCycles();

arch('catalog depends only on shared')
    ->expect(module('Catalog'))
    ->toOnlyDependOn(module('Shared'));

// With LARCH_STOP, a test that fails between two checks.
if (getenv('LARCH_STOP') === '1') {
    test('billing, then cycles', function () {
        expect(module('Billing'))->toOnlyDependOn(module('Shared'));
        expect('stopped')->toBe('running');
        expect(modules())->toHaveNoCycles();
    });
}
