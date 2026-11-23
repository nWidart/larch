<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Support\Facades\Facade;
use Nwidart\Larch\Edge;
use Nwidart\Larch\Exceptions\LarchException;
use Nwidart\Larch\Graph\Edge as GraphEdge;
use Nwidart\Larch\Graph\EdgeKind;
use Nwidart\Larch\Larch;
use Wired\Catalog\Pricing\PriceEngine;
use Wired\Shared\Facades\Stock;

use function Nwidart\Larch\module;

/*
 * The wired fixture is a Laravel app with four modules that reach each
 * other through a facade, a container key, a listener and a job. Larch
 * boots it from tests/Fixtures/wired/bootstrap/app.php.
 */

function wired(): Larch
{
    return Larch::reset()
        ->root(__DIR__.'/../Fixtures/wired')
        ->paths('Modules')
        ->modules('Wired\*');
}

beforeEach(fn () => wired());

afterEach(function () {
    HandleExceptions::flushState($this);
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);
    Container::setInstance(null);
});

it('sees only imports by default', function () {
    expect(module('Billing'))->toOnlyDependOn(module('Catalog'), module('Shared'), module('Shipping'));
});

it('lists the links the booted app makes', function () {
    $message = failureOf(fn () => expect(module('Billing'))
        ->toOnlyDependOn(module('Catalog')->publicApi(), module('Shared'))
        ->via(Edge::all()));

    expect($message)->toBe(<<<'TXT'
        Module Billing may only depend on Shared, Catalog (public API). 4 links break it:

          Modules/Billing/Actions/ChargeOrder.php:17
            Wired\Billing\Actions\ChargeOrder uses Wired\Catalog\Pricing\PriceEngine
            through the container key 'pricing', bound in Modules/Catalog/Providers/CatalogServiceProvider.php:13
            It is internal to Catalog. The public API of a module is: Contracts, Events.

          Modules/Billing/Actions/ChargeOrder.php:18
            Wired\Billing\Actions\ChargeOrder uses Wired\Inventory\Internal\StockLedger
            through the facade Wired\Shared\Facades\Stock, bound in Modules/Inventory/Providers/InventoryServiceProvider.php:13
            Billing may not depend on Inventory.

          Modules/Billing/Actions/ChargeOrder.php:22
            Wired\Billing\Actions\ChargeOrder uses Wired\Shipping\Jobs\SendLabel
            Billing may not depend on Shipping.

          Modules/Billing/Actions/ChargeOrder.php:22
            Wired\Billing\Actions\ChargeOrder dispatches Wired\Shipping\Jobs\SendLabel
            Billing may not depend on Shipping.
        TXT);
});

it('checks only the kinds given to via()', function () {
    $message = failureOf(fn () => expect(module('Billing'))
        ->toOnlyDependOn(module('Catalog'), module('Shared'), module('Shipping'))
        ->via(Edge::facades(), Edge::container()));

    expect($message)->toBe(<<<'TXT'
        Module Billing may only depend on Catalog, Shared, Shipping. 1 link breaks it:

          Modules/Billing/Actions/ChargeOrder.php:18
            Wired\Billing\Actions\ChargeOrder uses Wired\Inventory\Internal\StockLedger
            through the facade Wired\Shared\Facades\Stock, bound in Modules/Inventory/Providers/InventoryServiceProvider.php:13
            Billing may not depend on Inventory.
        TXT);
});

it('links a listener to its event, as the app registers it', function () {
    $message = failureOf(fn () => expect(module('Inventory'))->toIgnoreEventsOf(module('Billing')));

    expect($message)->toBe(<<<'TXT'
        Module Inventory ignores the events of Billing. 1 link breaks it:

          Modules/Inventory/Listeners/ReleaseHold.php:6
            Wired\Inventory\Listeners\ReleaseHold listens to Wired\Billing\Events\InvoiceIssued
            Inventory may not listen to events of Billing.
        TXT);
});

it('places a closure listener where it is registered', function () {
    expect(failureOf(fn () => expect(module('Shipping'))->toIgnoreEventsOf(module('Billing'))))
        ->toContain(<<<'TXT'
              Modules/Shipping/Providers/ShippingServiceProvider.php:8
                Wired\Shipping\Providers\ShippingServiceProvider listens to Wired\Billing\Events\InvoiceIssued
                registered in Modules/Shipping/Providers/ShippingServiceProvider.php:12
            TXT);
});

it('passes when a module listens to no event of the others', function () {
    expect(module('Catalog'))->toIgnoreEventsOf(module('Billing'), module('Inventory'));
});

it('finds cycles through runtime links', function () {
    expect(module('Billing'))->toHaveNoCycles();

    expect(failureOf(fn () => expect(module('Billing'))->toHaveNoCycles()->via(Edge::imports(), Edge::events())))
        ->toBe(<<<'TXT'
            Modules use each other in 1 cycle:

              Billing, Shipping
                Billing uses Shipping in 1 place:
                  Modules/Billing/Actions/ChargeOrder.php:22  Wired\Billing\Actions\ChargeOrder uses Wired\Shipping\Jobs\SendLabel
                Shipping uses Billing in 1 place:
                  Modules/Shipping/Providers/ShippingServiceProvider.php:8  Wired\Shipping\Providers\ShippingServiceProvider listens to Wired\Billing\Events\InvoiceIssued
            TXT);
});

it('keeps the links it cannot follow as unknown edges', function () {
    $unknown = array_values(array_filter(
        wired()->runtimeGraph()->edges,
        fn (GraphEdge $edge): bool => $edge->kind === EdgeKind::Unknown,
    ));

    expect(array_map(fn (GraphEdge $edge): array => [$edge->line, $edge->to, $edge->through, $edge->boundIn], $unknown))->toBe([
        [19, '', "app('discounts'): Larch cannot tell the class bound to 'discounts' without building it. Give the binding closure a return type", 'Modules/Catalog/Providers/CatalogServiceProvider.php:15'],
        [20, '', 'app($this->service): the container key is computed', null],
    ]);
});

it('skips a container key that names a class, and the dispatch of an event', function () {
    $edges = array_filter(
        wired()->runtimeGraph()->edges,
        fn (GraphEdge $edge): bool => $edge->kind !== EdgeKind::Import && $edge->from === 'Wired\Billing\Actions\ChargeOrder',
    );

    expect(array_map(fn (GraphEdge $edge): int => $edge->line, array_values($edges)))->toBe([17, 18, 19, 20, 22]);
});

it('reuses the app a test booted', function () {
    $app = require __DIR__.'/../Fixtures/wired/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $app->bind('stock', fn (): PriceEngine => new PriceEngine);
    Stock::clearResolvedInstances();

    $facades = wired()->runtimeGraph()->edges;
    $facade = array_values(array_filter($facades, fn (GraphEdge $edge): bool => $edge->kind === EdgeKind::Facade))[0];

    expect($facade->to)->toBe('Wired\Catalog\Pricing\PriceEngine');
});

it('asks for the app when it cannot boot one', function () {
    shop();

    expect(module('Billing'))->toOnlyDependOn(module('Shared'), module('Catalog'))->via(Edge::all());
})->throws(LarchException::class, 'Larch needs the booted app to read facades, the container, events and jobs.');

it('refuses via() with no kind', function () {
    expect(module('Billing'))->toOnlyDependOn(module('Shared'))->via();
})->throws(LarchException::class, 'via() needs at least one kind of link, such as Edge::all().');
