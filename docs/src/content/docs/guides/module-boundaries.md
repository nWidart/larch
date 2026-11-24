---
title: Guard module boundaries
description: Write rules on which modules a module may use, how modules use each other, and cycles between them.
---

Write module rules in Pest's `arch()` tests, for example in `tests/Arch/ModulesTest.php`.
[Define your modules](/larch/guides/define-modules/) first.

## Limit what a module may use

To list the modules a module may use, use `toOnlyDependOn()`:

```php
use function Nwidart\Larch\module;

arch('billing uses only shared and catalog')
    ->expect(module('Billing'))
    ->toOnlyDependOn(module('Shared'), module('Catalog'));
```

To allow only the public API of a module, pass `module('Name')->publicApi()`:

```php
arch('billing uses only shared, and the public API of catalog')
    ->expect(module('Billing'))
    ->toOnlyDependOn(module('Shared'), module('Catalog')->publicApi());
```

To forbid a module to use any other module, call `toOnlyDependOn()` with no target:

```php
arch('shared uses no other module')
    ->expect(module('Shared'))
    ->toOnlyDependOn();
```

## Keep internals internal

To make every module use the others only through their public API, use `toOnlyUsePublicApis()` on `modules()`:

```php
use function Nwidart\Larch\modules;

arch('modules use each other only through their public API')
    ->expect(modules())
    ->toOnlyUsePublicApis();
```

To check one module only, give `module('Name')` as the subject.

## Forbid cycles

To fail when modules use each other, directly or through other modules, use `toHaveNoCycles()`:

```php
arch('no module is in a cycle')
    ->expect(modules())
    ->toHaveNoCycles();
```

## Check more than imports

These rules check the classes the code names. To check facades, container keys, events and jobs too,
add `via()`. See [Check links the app makes](/larch/guides/runtime-links/).

## Related

- [Module rules reference](/larch/reference/module-rules/)
- [Failure messages](/larch/reference/failure-messages/)
