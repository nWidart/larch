# Changelog

## Unreleased

### Fixed

- `arch()->preset()->mortise()` reads `modules_statuses.json` as laravel-modules and Mortise do: a module is on only when its entry is `true`. A module without an entry is now off, and so is every module of an app without the file. Before, Larch counted them on. **This changes results:** an app without `modules_statuses.json`, or with modules it does not list, now gets no break from those modules, and no link to them breaks a rule. Add the file with each module set to `true` to keep checking them. An entry matches its module in any case, as in laravel-modules. A file that is not a JSON object of module names now stops the test, so CI does not pass with every module off.
- The shared fixture apps `defaults`, `custom-folders` and `all-off` gain a `modules_statuses.json` that turns every module on, so their `expected.json` holds for both readings. In `module-off`, Shipping has no entry instead of `false`.

## 0.2.0 - 2026-11-26

### Added

- `arch()->preset()->mortise()` reads the third rule of `mortise.json`, `allowed-use`: each module with a list may use only the modules of its list. A module without a list is not checked. A name that is no module breaks nothing. The shared fixture app `tests/Fixtures/mortise/allowed-use` holds the result Mortise and Larch both expect, and each `expected.json` gains the member `allowed-use`.

### Changed

- Code outside a class links too, as Mortise counts it: route files, config files, a migration that returns an anonymous class, and code beside a class. The link comes from the file, named by its path relative to the project root, in the module whose folder holds it: `Modules/Audit/routes/web.php uses Modules\Billing\Http\Controllers\InvoiceController`. Failure messages, baseline entries and `larch:graph` name the file. Baseline entries of today keep matching, and the export format keeps version 1. A rule that passed can now fail on a route file: fix the link or run `--update-larch-baseline`. With laravel-modules 11 and later, add `larch()->paths('app', 'Modules')` to read the route files of each module.
- Blade views, `*.blade.php`, are no longer read as PHP. Mortise skips them too.

### Fixed

- An unused import of a namespace, such as `use Modules\Billing\Contracts;`, no longer links to a class of that name. Mortise leaves it out too.
- `Larch::links()` reuses the links it found for the same kinds, so the preset no longer builds them again for each module with a list.
- The graph export guide no longer says that Mortise reads the JSON format. It does not.
- A folder given as `./Modules` gives the same file paths as `Modules`, in messages and in the baseline.

## 0.1.0 - 2026-11-24

### Added

- `php artisan larch:graph`, registered by package discovery: `--json` prints the graph of modules in a versioned format, `--html` writes a self-contained report with the graph and every link.

- `arch()->preset()->mortise()` runs the rules of `mortise.json`: `no-circular-use` and `public-surface-only` with its folders. Shared fixture apps in `tests/Fixtures/mortise` hold the result Mortise and Larch both expect.

- App rules on the booted app: `larch()->routes()` with `toUseMiddleware()`, `larch()->models()` with `toHavePolicy()` and `toBeInMorphMap()`, `larch()->jobs()` with `toDefine()`, and `larch()->listenersOf()` with `toBeQueued()`. Each takes the baseline.

- Runtime pass: Larch reads the booted app for the class behind each facade and container key, the listeners of each event, and the jobs a class dispatches. Calls it cannot follow are kept as unknown links with their call site.
- `via(Edge::all())` and the other kinds of `Nwidart\Larch\Edge`, after `toOnlyDependOn()`, `toOnlyUsePublicApis()` and `toHaveNoCycles()`. Rules check imports only by default.
- `toIgnoreEventsOf(...$modules)`: a module listens to no event of the given modules. It replaces the planned `not->toListenTo()`, whose failure message would lose the list of links.

- Graph of links between classes, read from the code with `nikic/php-parser`.
- Configuration through `larch()`: modules by namespace pattern or by name, public API namespaces, folders to read and to skip.
- Baseline in `larch-baseline.json`, written with `--update-larch-baseline`. A test fails only on violations that are not in it, and the run lists the entries that no violation matches any more. `larch()->baseline()` moves the file.
- Pest expectations `toOnlyDependOn()`, `toOnlyUsePublicApis()` and `toHaveNoCycles()`, with failure messages that list each link with its file and line.
