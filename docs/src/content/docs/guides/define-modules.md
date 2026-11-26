---
title: Define your modules
description: Tell Larch which namespaces are modules, which part of each module is public, and which folders to read.
---

Configure Larch once, in `tests/Pest.php`. Every rule of the run uses this configuration.

## Modules under one namespace

When each module is a namespace under one parent, give the parent with `\*`:

```php title="tests/Pest.php"
use function Nwidart\Larch\larch;

larch()->modules('App\Domain\*');
```

Each namespace directly under `App\Domain` is a module, named by that namespace: `App\Domain\Billing` is `Billing`.

## With nwidart/laravel-modules

With `nwidart/laravel-modules` installed, call `modules()` with no argument. It uses `Modules\*`:

```php title="tests/Pest.php"
larch()->modules();
```

`nwidart/laravel-modules` defines a global `module()` helper. Import Larch's functions with `use function`
in each test file, so PHP does not call the global one:

```php
use function Nwidart\Larch\module;
use function Nwidart\Larch\modules;
```

## Modules in different places

When modules do not share a parent namespace, name each one:

```php title="tests/Pest.php"
larch()->modules([
    'Billing' => 'App\Billing',
    'Catalog' => 'App\Catalog',
    'Shared' => 'Support\Shared',
]);
```

## Set the public API

The public API is the part of a module other modules may use. List its namespaces, relative to each module:

```php title="tests/Pest.php"
larch()
    ->modules('Modules\*')
    ->publicApi('Contracts', 'Events', 'Data');
```

`Contracts` means `Modules\Billing\Contracts` for Billing, and `Modules\Catalog\Contracts` for Catalog.
Without `publicApi()`, the public API is `Contracts` and `Events`.

## Choose the folders to read

Larch reads every PSR-4 folder of the project outside `vendor`. To read only some folders:

```php title="tests/Pest.php"
larch()->paths('app', 'Modules');
```

With laravel-modules 11 and later, the PSR-4 folder of a module is `Modules/<Name>/app`, so the default leaves out its route files.
Read `Modules` to check them too. A route file of a module belongs to the module whose folder holds it.

Larch skips folders named `vendor`, `node_modules`, `tests` and `Tests` wherever they appear.
To skip other folders, replace that list:

```php title="tests/Pest.php"
larch()->exclude('vendor', 'node_modules', 'tests', 'Tests', 'Legacy');
```

## Related

- [Configuration reference](/larch/reference/configuration/)
- [About modules and public APIs](/larch/concepts/modules/)
