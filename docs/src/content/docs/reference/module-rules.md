---
title: Module rules
description: The expectations that check links between modules, and via().
---

Module rules check links between two modules. Links to vendor classes, and to code outside every module, are not checked.

| Expectation | Subject | Takes `via()` | Reads the booted app |
|---|---|---|---|
| [`toOnlyDependOn()`](#toonlydependon) | `module()` | Yes | With `via()` |
| [`toOnlyUsePublicApis()`](#toonlyusepublicapis) | `module()`, `modules()` | Yes | With `via()` |
| [`toHaveNoCycles()`](#tohavenocycles) | `module()`, `modules()` | Yes | With `via()` |
| [`toIgnoreEventsOf()`](#toignoreeventsof) | `module()` | No | Always |

## `toOnlyDependOn()`

```php
expect(module('Billing'))->toOnlyDependOn(Module|PublicApi ...$targets)
```

Fails when the module uses a module that is not a target.

- A target `module('Name')` allows the whole module.
- A target `module('Name')->publicApi()` allows only the public API of that module.
- No target allows no other module.

Headline: `Module Billing may only depend on Shared, Catalog (public API).`
Each link: `Billing may not depend on Inventory.`, or, for a class outside an allowed public API, `It is internal to Catalog. The public API of a module is: Contracts, Events.`

## `toOnlyUsePublicApis()`

```php
expect(modules())->toOnlyUsePublicApis()
```

Fails when the module uses a class of another module outside the public API of that module.

Headline: `Modules use other modules only through their public API.`, or `Module Billing uses other modules only through their public API.`
Each link: `It is internal to Catalog. The public API of a module is: Contracts, Events.`

## `toHaveNoCycles()`

```php
expect(modules())->toHaveNoCycles()
```

Fails when modules use each other, directly or through other modules.
With `module('Name')`, fails only on the cycles that hold that module.

The message lists each cycle, then the links of each step. See [Failure messages](/larch/reference/failure-messages/#cycles).

## `toIgnoreEventsOf()`

```php
expect(module('Inventory'))->toIgnoreEventsOf(Module ...$modules)
```

Fails when a listener of the module listens to an event of one of the modules. Takes at least one module.
Reads the listeners the booted app registers.

Headline: `Module Inventory ignores the events of Billing.`
Each link: `Inventory may not listen to events of Billing.`

## `via()`

```php
->via(Edge ...$kinds)
```

The kinds of link the rule checks. Comes right after `toOnlyDependOn()`, `toOnlyUsePublicApis()` or `toHaveNoCycles()`, before `and()`.
Takes at least one kind. Without `via()`, a rule checks `Edge::imports()`.

The kinds are listed in [Link kinds](/larch/reference/link-kinds/).

## No `not->`

Rules that forbid something have positive names, such as `toHaveNoCycles()`. Pest's `not->` replaces the failure message, and the list of links would be lost.
