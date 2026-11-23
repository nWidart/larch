# Larch

Architecture rules for Laravel apps, as a Pest plugin. Larch checks which module uses which, and prints every link that breaks a rule with the file and line it comes from.

Larch is in early development. This release reads the code only: it finds links from class names in code (types, `new`, static calls, `::class`, attributes, `extends`, `implements`). Later releases boot the app to find links made through facades, the container, events and queued jobs.

## Install

```bash
composer require nwidart/larch --dev
```

Larch needs PHP 8.3 or later and Pest 4.3 or later.

## Configure

Name the modules of the app once, in `tests/Pest.php`:

```php
use function Nwidart\Larch\larch;

larch()
    ->modules('Modules\*')                // each namespace under Modules is a module
    ->publicApi('Contracts', 'Events');   // what other modules may use, inside each module
```

- `modules('App\Domain\*')` makes each namespace directly under `App\Domain` a module, named by that namespace.
- `modules(['Billing' => 'App\Billing', 'Catalog' => 'App\Catalog'])` names the modules one by one.
- With `nwidart/laravel-modules` installed, `modules()` with no argument uses `Modules\*`.
- `publicApi()` lists namespaces relative to each module: `Contracts` means `Modules\Billing\Contracts` for Billing. The default is `Contracts` and `Events`.
- Larch reads every PSR-4 folder of the project outside `vendor`. Change that with `paths('app', 'Modules')`. Folders named `vendor`, `node_modules`, `tests` and `Tests` are skipped; replace that list with `exclude(...)`.

## Write rules

```php
use function Nwidart\Larch\module;
use function Nwidart\Larch\modules;

arch('billing uses only shared, and the public API of catalog')
    ->expect(module('Billing'))
    ->toOnlyDependOn(module('Shared'), module('Catalog')->publicApi());

arch('modules use each other only through their public API')
    ->expect(modules())
    ->toOnlyUsePublicApis();

arch('no module is in a cycle')
    ->expect(modules())
    ->toHaveNoCycles();
```

| Expectation | Subject | Fails when |
|---|---|---|
| `toOnlyDependOn(...$targets)` | `module('Name')` | The module uses a module that is not a target. A target `module('Name')->publicApi()` allows only that module's public API. |
| `toOnlyUsePublicApis()` | `module('Name')` or `modules()` | The module uses a class of another module outside its public API. |
| `toHaveNoCycles()` | `module('Name')` or `modules()` | Modules use each other, directly or through other modules. |

Only links between two modules are checked. Links to vendor classes and to code outside every module are not.

Rules that forbid something have positive names, such as `toHaveNoCycles()`. Pest's `not->` replaces the failure message, so a rule written with `not->` would lose the list of links.

`module()` and `modules()` are namespaced functions. Import them with `use function`: `nwidart/laravel-modules` already defines a global `module()` helper.

## What a failure looks like

```
FAILED  Tests\Arch\ModulesTest > billing uses only shared, and the public API of catalog
Module Billing may only depend on Shared, Catalog (public API). 1 link breaks it:

  Modules/Billing/app/Actions/ChargeOrder.php:16 (also line 17)
    Modules\Billing\Actions\ChargeOrder uses Modules\Catalog\Pricing\PriceEngine
    It is internal to Catalog. The public API of a module is: Contracts, Events.
```

## Develop

```bash
composer test       # Pest
composer lint       # Pint, check only
composer analyse    # PHPStan
```

## License

MIT. See [LICENSE.md](LICENSE.md).
