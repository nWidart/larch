# Changelog

## Unreleased

### Added

- `arch()->preset()->mortise()` runs the rules of `mortise.json`: `no-circular-use` and `public-surface-only` with its folders. Shared fixture apps in `tests/Fixtures/mortise` hold the result Mortise and Larch both expect.

- App rules on the booted app: `larch()->routes()` with `toUseMiddleware()`, `larch()->models()` with `toHavePolicy()` and `toBeInMorphMap()`, `larch()->jobs()` with `toDefine()`, and `larch()->listenersOf()` with `toBeQueued()`. Each takes the baseline.

- Runtime pass: Larch reads the booted app for the class behind each facade and container key, the listeners of each event, and the jobs a class dispatches. Calls it cannot follow are kept as unknown links with their call site.
- `via(Edge::all())` and the other kinds of `Nwidart\Larch\Edge`, after `toOnlyDependOn()`, `toOnlyUsePublicApis()` and `toHaveNoCycles()`. Rules check imports only by default.
- `toIgnoreEventsOf(...$modules)`: a module listens to no event of the given modules. It replaces the planned `not->toListenTo()`, whose failure message would lose the list of links.

- Graph of links between classes, read from the code with `nikic/php-parser`.
- Configuration through `larch()`: modules by namespace pattern or by name, public API namespaces, folders to read and to skip.
- Baseline in `larch-baseline.json`, written with `--update-larch-baseline`. A test fails only on violations that are not in it, and the run lists the entries that no violation matches any more. `larch()->baseline()` moves the file.
- Pest expectations `toOnlyDependOn()`, `toOnlyUsePublicApis()` and `toHaveNoCycles()`, with failure messages that list each link with its file and line.
