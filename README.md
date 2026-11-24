# Larch

Architecture rules for Laravel apps, as a Pest plugin. Larch checks which module uses which, and prints every link that breaks a rule with the file and line it comes from.

Larch is in early development. It finds links in two ways:

- From the code: class names in types, `new`, static calls, `::class`, attributes, `extends` and `implements`.
- From the booted app, with `via()`: the class behind a facade or a container key, the listeners of each event, and the jobs a class dispatches. Import-based tools such as Pest's `arch()`, Deptrac and PHPat cannot see these.

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
| `toIgnoreEventsOf(...$modules)` | `module('Name')` | A listener of the module listens to an event of one of the modules. Reads the booted app. |

Only links between two modules are checked. Links to vendor classes and to code outside every module are not.

Rules that forbid something have positive names, such as `toHaveNoCycles()`. Pest's `not->` replaces the failure message, so a rule written with `not->` would lose the list of links.

`module()` and `modules()` are namespaced functions. Import them with `use function`: `nwidart/laravel-modules` already defines a global `module()` helper.

## Links the app makes

By default a rule checks imports only. Add `via()` to check the links the booted app makes too:

```php
use Nwidart\Larch\Edge;

arch('billing uses only shared, and the public API of catalog')
    ->expect(module('Billing'))
    ->toOnlyDependOn(module('Shared'), module('Catalog')->publicApi())
    ->via(Edge::all());

arch('inventory does not react to billing events')
    ->expect(module('Inventory'))
    ->toIgnoreEventsOf(module('Billing'));
```

| Kind | Link |
|---|---|
| `Edge::imports()` | A class named in the code. The default. |
| `Edge::facades()` | A static call on a facade, to the class bound to the facade's key. Real-time facades too. Laravel's own facades are skipped. |
| `Edge::container()` | `app('key')`, `resolve('key')`, `App::make('key')` or `$this->app->make('key')` with a string key, to the class bound to the key. A key that names a class is already an import. |
| `Edge::events()` | A listener, to the event it listens to, as the app registers it: `Event::listen()`, event discovery and closures. A wildcard such as `'App\Billing\Events\*'` links the listener to each scanned class it matches; a pattern with no namespace before its `*` is skipped. |
| `Edge::jobs()` | `Job::dispatch()`, `dispatch(new Job)` or `Bus::dispatch(new Job)`, to the job. |
| `Edge::all()` | All of the above. |

`via()` takes several kinds: `via(Edge::imports(), Edge::events())`. It works after `toOnlyDependOn()`, `toOnlyUsePublicApis()` and `toHaveNoCycles()`.

How Larch reads the app:

- It reuses the app a Laravel test case booted. Otherwise it boots `bootstrap/app.php` itself, in the `testing` environment and without the cached configuration, then restores the environment variables of the process. A test that boots another app gets a graph read from that app. Run the rules in a test that uses the Laravel test case if your providers need more set up.
- It never builds a service. It reads the class a key is bound to: `bind('pricing', PriceEngine::class)`, or the return type of a binding closure, `singleton('stock', fn (): StockLedger => ...)`. Give binding closures a return type so Larch can follow them.
- A call it cannot follow, such as `app($class)` or a binding closure with no return type, becomes an unknown link. Unknown links keep their call site. No rule checks them; `larch:graph` will list them.
- A failure says how a link is made, and where the app binds it:

```
  Modules/Billing/app/Actions/ChargeOrder.php:18
    Modules\Billing\Actions\ChargeOrder uses Modules\Inventory\Internal\StockLedger
    through the facade Modules\Shared\Facades\Stock, bound in Modules/Inventory/app/Providers/InventoryServiceProvider.php:13
    Billing may not depend on Inventory.
```

## App rules

The booted app also holds routes, policies, jobs, listeners and the morph map. Rules on them read like the module rules:

```php
use Illuminate\Database\Eloquent\Relations\Pivot;

use function Nwidart\Larch\larch;

arch('api routes need auth')
    ->expect(larch()->routes()->prefixed('api')->except('api/webhooks/*', 'api/health'))
    ->toUseMiddleware('auth:sanctum');

arch('every model has a policy')
    ->expect(larch()->models()->except(Pivot::class))
    ->toHavePolicy();

arch('queued jobs set retry limits')
    ->expect(larch()->jobs()->queued())
    ->toDefine('tries', 'timeout');

arch('listeners on billing events are queued')
    ->expect(larch()->listenersOf('Modules\Billing\Events\*'))
    ->toBeQueued();

arch('polymorphic models are in the morph map')
    ->expect(larch()->models()->usedInMorphs())
    ->toBeInMorphMap();
```

| Subject | Narrow with | Expectation | Fails when |
|---|---|---|---|
| `larch()->routes()` | `prefixed('api')`, `named('admin.*')`, `except('api/health', 'admin.login')` | `toUseMiddleware(...$middleware)` | A route does not run one of the middleware. Give a name with its parameters (`auth:sanctum`), a group (`api`) or a class. A group counts only when `withoutMiddleware()` removes none of its members. |
| `larch()->models()` | `except(Pivot::class, 'App\Legacy\*')` | `toHavePolicy()` | Laravel finds no policy for a model, registered with `Gate::policy()` or found by name. |
| `larch()->jobs()` | `queued()` | `toDefine(...$members)` | A job does not declare the member in a form the queue reads: a property, a queue attribute such as `#[Tries(3)]`, or, for `tries`, `backoff` and `retryUntil`, a method. |
| `larch()->listenersOf(...$events)` | | `toBeQueued()` | A listener of a matching event does not implement `ShouldQueue`, or is registered as an object, which Laravel calls at once. Wildcard registrations count. Closure listeners are skipped. |
| `larch()->models()` | `usedInMorphs()` | `toBeInMorphMap()` | A model that a morph relation stores by class has no alias in the morph map. |

- Models are the classes Larch reads that extend `Model`. Jobs are the classes that use the `Dispatchable` trait of the bus.
- `usedInMorphs()` keeps the models that declare `morphOne`, `morphMany` or `morphToMany`, and the models named in `morphedByMany`.
- The baseline works for app rules too. A route's entry names its methods and URI; a class's entry names the class.

## What a failure looks like

```
FAILED  Tests\Arch\ModulesTest > billing uses only shared, and the public API of catalog
Module Billing may only depend on Shared, Catalog (public API). 1 link breaks it:

  Modules/Billing/app/Actions/ChargeOrder.php:16 (also line 17)
    Modules\Billing\Actions\ChargeOrder uses Modules\Catalog\Pricing\PriceEngine
    It is internal to Catalog. The public API of a module is: Contracts, Events.
```

## Baseline

An existing app breaks rules on its first run. A baseline accepts today's violations, so a test fails only on new ones.

```bash
./vendor/bin/pest tests/Arch --update-larch-baseline
```

This writes `larch-baseline.json` at the project root. Commit it. Each entry is a test name and a fingerprint: the class that uses, the class it uses, and the kind of link. There is no line number, so an edit above a call does not make its violation new.

```json
{
    "version": 1,
    "tests": {
        "Tests\\Arch\\ModulesTest > billing uses only shared": [
            {
                "from": "Modules\\Billing\\Actions\\ChargeOrder",
                "to": "Modules\\Catalog\\Pricing\\PriceEngine",
                "kind": "import"
            }
        ]
    }
}
```

With a baseline:

- A test fails only on violations that are not in it. The failure message says how many more are in the baseline.
- After the tests, Pest prints how many violations the baseline accepted, and lists the entries of passing tests that no violation matches any more. The next `--update-larch-baseline` removes them.
- `--update-larch-baseline` replaces the entries of the tests that pass, and keeps those of the others. A test that fails may stop before a check, so it keeps its entries and gains the new ones. Delete the file first to start over.
- The baseline is written once the tests are done, through a temporary file, so a failed write leaves it as it was.
- `--update-larch-baseline` works with `--parallel`. With `--parallel`, the lines printed after the tests are missing, because each worker keeps its own count.

Move the file with `larch()->baseline('tests/Arch/baseline.json')`.

## Develop

```bash
composer test       # Pest
composer lint       # Pint, check only
composer analyse    # PHPStan
```

## License

MIT. See [LICENSE.md](LICENSE.md).
