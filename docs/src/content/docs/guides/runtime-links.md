---
title: Check links the app makes
description: Make module rules see facades, container keys, event listeners and dispatched jobs, which link modules without an import.
---

By default a module rule checks imports: the classes the code names.
A facade, a container key, an event listener or a dispatched job can link two modules without an import.
Larch reads these links from the booted app. This needs `laravel/framework`.

## Check every kind of link

Add `via(Edge::all())` after the rule:

```php
use Nwidart\Larch\Edge;

use function Nwidart\Larch\module;

arch('billing uses only shared, and the public API of catalog')
    ->expect(module('Billing'))
    ->toOnlyDependOn(module('Shared'), module('Catalog')->publicApi())
    ->via(Edge::all());
```

`via()` works after `toOnlyDependOn()`, `toOnlyUsePublicApis()` and `toHaveNoCycles()`.

## Check some kinds of link

Pass the kinds you want:

```php
arch('no module is in a cycle')
    ->expect(modules())
    ->toHaveNoCycles()
    ->via(Edge::imports(), Edge::events());
```

The kinds are listed in [Link kinds](/larch/reference/link-kinds/).

## Forbid a module to react to the events of another

Use `toIgnoreEventsOf()`. It always reads the booted app:

```php
arch('inventory does not react to billing events')
    ->expect(module('Inventory'))
    ->toIgnoreEventsOf(module('Billing'));
```

## Let Larch follow your bindings

Larch never builds a service. It reads the class a key is bound to.

- Bind to a class name: `$this->app->bind('pricing', PriceEngine::class)`.
- Give each binding closure a return type: `$this->app->singleton('stock', fn (): StockLedger => new StockLedger)`.

A binding closure with no return type, or a call such as `app($class)`, becomes an unknown link.
No rule checks unknown links. To list them, run [`php artisan larch:graph`](/larch/guides/graph-export/).

## When providers need more set up

Larch reuses the app that a Laravel test case booted. Otherwise it boots `bootstrap/app.php` itself, in the `testing` environment.
If your providers need more than that to boot, run the rules in a test that uses the Laravel test case:

```php title="tests/Pest.php"
pest()->extend(Tests\TestCase::class)->in('Feature', 'Arch');
```

## Related

- [How Larch reads your app](/larch/concepts/reading-the-app/)
- [Link kinds](/larch/reference/link-kinds/)
