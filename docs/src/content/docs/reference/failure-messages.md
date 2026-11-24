---
title: Failure messages
description: The layout of the message a failed Larch rule prints.
---

A failed rule prints a headline, then one block per violation. Paths are relative to the [project root](/larch/reference/configuration/#root).

## Links

Module rules, except `toHaveNoCycles()`:

```
Module Billing may only depend on Shared, Catalog (public API). 1 link breaks it:

  Modules/Billing/app/Actions/ChargeOrder.php:18 (also line 21)
    Modules\Billing\Actions\ChargeOrder uses Modules\Inventory\Internal\StockLedger
    through the facade Modules\Shared\Facades\Stock, bound in Modules/Inventory/app/Providers/InventoryServiceProvider.php:13
    Billing may not depend on Inventory.
```

| Line | Holds |
|---|---|
| Headline | What the rule asks, and how many links break it. |
| Place | The file and line of the first place of the link, and the other lines in the same file. |
| Sentence | The class that uses, the [verb of the kind](/larch/reference/link-kinds/), and the class it uses. |
| How | For a link that is not an import: the facade or container key it goes `through`, and where it is `bound in` or `registered in`. Left out for imports. |
| Reason | Why this link breaks the rule. |

## Cycles

```
Modules use each other in 1 cycle:

  Billing, Catalog
    Billing uses Catalog in 2 places:
      Modules/Billing/app/Actions/ChargeOrder.php:16  Modules\Billing\Actions\ChargeOrder uses Modules\Catalog\Pricing\PriceEngine
      ...
    Catalog uses Billing in 1 place:
      ...
```

Each cycle lists its modules, then each step between two of them with up to five links. More links are counted: `and 3 more`.

## App rules

```
Routes under api, except api/health, use the middleware auth:sanctum. 1 route breaks it:

  GET|POST api/orders
    App\Http\Controllers\OrderController@store
    Middleware: api
```

The place is the route's methods and URI, or the file and line of the class. The lines below it say what is missing.

## With a baseline

When the [baseline](/larch/reference/baseline/) accepts some violations, the message lists only the new ones, and ends with:

```
2 more violations are in the baseline, larch-baseline.json.
```

## In the Mortise preset

Each rule of [`mortise.json`](/larch/reference/mortise/) that fails has its own part, under its name:

```
mortise.json, no-circular-use:
Modules use each other in 1 cycle:
...
```
