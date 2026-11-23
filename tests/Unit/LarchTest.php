<?php

declare(strict_types=1);

use Nwidart\Larch\Exceptions\LarchException;
use Nwidart\Larch\Larch;

it('makes each namespace under the pattern a module', function () {
    expect(shop()->moduleMap())->toBe([
        'Billing' => 'Modules\Billing',
        'Catalog' => 'Modules\Catalog',
        'Checkout' => 'Modules\Checkout',
        'Shared' => 'Modules\Shared',
        'Shipping' => 'Modules\Shipping',
    ]);
});

it('takes modules as names and namespaces', function () {
    $larch = shop()->modules(['Money' => 'Modules\Shared', 'Pay' => '\Modules\Billing\\']);

    expect($larch->moduleMap())->toBe(['Money' => 'Modules\Shared', 'Pay' => 'Modules\Billing'])
        ->and($larch->moduleOf('Modules\Billing\Models\Invoice'))->toBe('Pay')
        ->and($larch->moduleOf('Modules\Catalog\Pricing\PriceEngine'))->toBeNull();
});

it('refuses a pattern without a wildcard', function () {
    Larch::reset()->modules('Modules');
})->throws(LarchException::class, "larch()->modules() takes a pattern that ends in \\*, such as 'Modules\\*'");

it('names the modules it found when a module is unknown', function () {
    shop()->namespaceOf('Inventory');
})->throws(LarchException::class, 'There is no module named Inventory. Modules found: Billing, Catalog, Checkout, Shared, Shipping.');

it('asks for the modules when laravel-modules is not installed', function () {
    Larch::reset()->root(__DIR__.'/../Fixtures/shop')->paths('Modules')->moduleMap();
})->throws(LarchException::class, "Call larch()->modules('App\\Modules\\*') in tests/Pest.php.");

it('tells public classes from internal ones', function () {
    $larch = shop()->publicApi('Contracts', '\Pricing\\');

    expect($larch->isPublic('Modules\Catalog\Contracts\PricesOrders', 'Catalog'))->toBeTrue()
        ->and($larch->isPublic('Modules\Catalog\Pricing\PriceEngine', 'Catalog'))->toBeTrue()
        ->and($larch->isPublic('Modules\Catalog\Models\Product', 'Catalog'))->toBeFalse()
        ->and($larch->isPublic('Modules\Catalog\ContractsHelper', 'Catalog'))->toBeFalse();
});

it('groups the links between modules by pair of classes', function () {
    $links = shop()->links();

    $pairs = array_map(fn ($link): string => "{$link->fromClass} > {$link->toClass} (".count($link->edges).')', $links);

    expect($pairs)->toBe([
        'Modules\Billing\Actions\ChargeOrder > Modules\Catalog\Contracts\PricesOrders (1)',
        'Modules\Billing\Actions\ChargeOrder > Modules\Catalog\Pricing\PriceEngine (2)',
        'Modules\Billing\Actions\ChargeOrder > Modules\Shared\Money\Money (1)',
        'Modules\Billing\Models\Invoice > Modules\Shared\Money\Money (1)',
        'Modules\Checkout\Actions\PlaceOrder > Modules\Shipping\Contracts\Ships (1)',
        'Modules\Shipping\Listeners\CreateShipment > Modules\Checkout\Events\OrderPaid (1)',
    ]);
});

it('reads the PSR-4 folders of the project by default', function () {
    $graph = Larch::reset()->modules('Nwidart\*')->graph();

    expect($graph->has('Nwidart\Larch\Larch'))->toBeTrue()
        ->and($graph->has('PhpParser\Parser'))->toBeFalse();
});
