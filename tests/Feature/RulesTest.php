<?php

declare(strict_types=1);

use Nwidart\Larch\Exceptions\LarchException;
use PHPUnit\Framework\ExpectationFailedException;

use function Nwidart\Larch\module;
use function Nwidart\Larch\modules;

beforeEach(fn () => shop());

function failureOf(Closure $expectation): string
{
    try {
        $expectation();
    } catch (ExpectationFailedException $failure) {
        return $failure->getMessage();
    }

    throw new RuntimeException('The expectation passed.');
}

describe('toOnlyDependOn', function () {
    it('passes when every link goes to an allowed module', function () {
        expect(module('Catalog'))->toOnlyDependOn(module('Shared'))
            ->and(module('Billing'))->toOnlyDependOn(module('Shared'), module('Catalog'));
    });

    it('lists the links to the internals of a module allowed through its public API', function () {
        $message = failureOf(fn () => expect(module('Billing'))->toOnlyDependOn(module('Shared'), module('Catalog')->publicApi()));

        expect($message)->toBe(<<<'TXT'
            Module Billing may only depend on Shared, Catalog (public API). 1 link breaks it:

              Modules/Billing/app/Actions/ChargeOrder.php:16 (also line 17)
                Modules\Billing\Actions\ChargeOrder uses Modules\Catalog\Pricing\PriceEngine
                It is internal to Catalog. The public API of a module is: Contracts, Events.
            TXT);
    });

    it('lists the links to modules that are not allowed', function () {
        $message = failureOf(fn () => expect(module('Billing'))->toOnlyDependOn(module('Shared')));

        expect($message)->toBe(<<<'TXT'
            Module Billing may only depend on Shared. 2 links break it:

              Modules/Billing/app/Actions/ChargeOrder.php:12
                Modules\Billing\Actions\ChargeOrder uses Modules\Catalog\Contracts\PricesOrders
                Billing may not depend on Catalog.

              Modules/Billing/app/Actions/ChargeOrder.php:16 (also line 17)
                Modules\Billing\Actions\ChargeOrder uses Modules\Catalog\Pricing\PriceEngine
                Billing may not depend on Catalog.
            TXT);
    });

    it('says when a module may depend on no other module', function () {
        expect(failureOf(fn () => expect(module('Checkout'))->toOnlyDependOn()))
            ->toStartWith('Module Checkout may only depend on no other module. 1 link breaks it:');
    });

    it('refuses a subject that is not a module', function () {
        expect('Modules\Billing')->toOnlyDependOn(module('Shared'));
    })->throws(LarchException::class, "toOnlyDependOn() expects module('Name') as its subject, got string.");

    it('refuses a target that is not a module', function () {
        expect(module('Billing'))->toOnlyDependOn('Modules\Shared');
    })->throws(LarchException::class, "toOnlyDependOn() takes module('Name') or module('Name')->publicApi(), got string.");

    it('refuses an unknown module', function () {
        expect(module('Billing'))->toOnlyDependOn(module('Inventory'));
    })->throws(LarchException::class, 'There is no module named Inventory.');
});

describe('toOnlyUsePublicApis', function () {
    it('passes when a module uses only public classes of others', function () {
        expect(module('Checkout'))->toOnlyUsePublicApis()
            ->and(module('Catalog'))->toOnlyUsePublicApis();
    });

    it('lists every link to the internals of another module', function () {
        $message = failureOf(fn () => expect(modules())->toOnlyUsePublicApis());

        expect($message)->toBe(<<<'TXT'
            Modules use other modules only through their public API. 3 links break it:

              Modules/Billing/app/Actions/ChargeOrder.php:16 (also line 17)
                Modules\Billing\Actions\ChargeOrder uses Modules\Catalog\Pricing\PriceEngine
                It is internal to Catalog. The public API of a module is: Contracts, Events.

              Modules/Billing/app/Actions/ChargeOrder.php:19
                Modules\Billing\Actions\ChargeOrder uses Modules\Shared\Money\Money
                It is internal to Shared. The public API of a module is: Contracts, Events.

              Modules/Billing/app/Models/Invoice.php:9
                Modules\Billing\Models\Invoice uses Modules\Shared\Money\Money
                It is internal to Shared. The public API of a module is: Contracts, Events.
            TXT);
    });

    it('follows the public API set in the configuration', function () {
        shop()->publicApi('Contracts', 'Events', 'Money', 'Pricing');

        expect(modules())->toOnlyUsePublicApis();
    });
});

describe('toHaveNoCycles', function () {
    it('passes for a module outside every cycle', function () {
        expect(module('Billing'))->toHaveNoCycles();
    });

    it('lists both directions of a cycle', function () {
        $message = failureOf(fn () => expect(modules())->toHaveNoCycles());

        expect($message)->toBe(<<<'TXT'
            Modules use each other in 1 cycle:

              Checkout, Shipping
                Checkout uses Shipping in 1 place:
                  Modules/Checkout/app/Actions/PlaceOrder.php:10  Modules\Checkout\Actions\PlaceOrder uses Modules\Shipping\Contracts\Ships
                Shipping uses Checkout in 1 place:
                  Modules/Shipping/app/Listeners/CreateShipment.php:9  Modules\Shipping\Listeners\CreateShipment uses Modules\Checkout\Events\OrderPaid
            TXT);
    });

    it('reports a cycle for a module in it', function () {
        expect(failureOf(fn () => expect(module('Shipping'))->toHaveNoCycles()))
            ->toStartWith('Modules use each other in 1 cycle:');
    });
});
