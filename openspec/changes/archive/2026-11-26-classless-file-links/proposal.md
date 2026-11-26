# Proposal

## Why

Larch links a class to the classes it names, and skips code outside a class. A route file such as `Modules/Audit/routes/web.php` declares no class, so its imports link nothing. Mortise gives each file under a module's folder to that module, classless files included, and counts every class the file names. On an app where a route file of Audit imports a controller of Billing, Mortise reports Audit using Billing and Larch does not. The gap came up in review of PR #11 and is documented as a known difference today.

## What Changes

- Code outside every named class links from the file itself: the source of the link is the file's path, relative to the project root. This covers files that declare no class (route files, config files, migrations that return an anonymous class) and code beside a class in a file that declares one.
- An import that no code of a classless file uses links the file to the imported class, as Mortise counts it.
- A file belongs to the module whose folder holds it. Larch finds the folder of a module from the files of its classes: the nearest folder above the file that holds classes, when all those classes belong to one module.
- Failure messages, baseline entries, `larch:graph` and the graph export name the file where they name a class today: `Modules/Audit/routes/web.php uses Modules\Billing\Http\Controllers\InvoiceController`. Baseline entries of today keep matching. The export format keeps version 1.
- Blade views (`*.blade.php`) are no longer read as PHP, as Mortise skips them.
- **BREAKING** (results): an app may get new links, so a rule that passed can fail until the link is fixed or added to the baseline.
- The shared fixture app `allowed-use` gains a route file of Audit that imports a class of Billing.
- Docs: the notes on the gap in the Mortise and link kinds references go away; the guides that read links say how route files count.

## Capabilities

### New Capabilities

- `code-links`: which links Larch reads from the code, from what source, and to which module each source belongs.

### Modified Capabilities

None. `mortise-preset` checks its rules on the links of `code-links`; its requirements do not change.

## Impact

- `src/Graph/ReferenceCollector.php`, `src/Graph/GraphBuilder.php`, `src/Graph/Edge.php`, `src/Larch.php`, `src/Modules/ModuleLink.php`.
- `tests/Unit/GraphBuilderTest.php`, `tests/Feature/RulesTest.php`, `tests/Feature/MortiseTest.php`, `tests/Fixtures/mortise/allowed-use`, `tests/Fixtures/shop`.
- `docs/src/content/docs/reference/link-kinds.md`, `reference/mortise.md`, `reference/baseline.md`, `reference/graph-command.md`, `concepts/reading-the-app.md`, `guides/define-modules.md`, `guides/mortise.md`, `CHANGELOG.md`.
- Mortise task 33.1 copies the changed fixture app and its `expected.json`.
