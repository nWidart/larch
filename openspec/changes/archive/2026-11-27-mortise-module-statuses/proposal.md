# Proposal

## Why

The preset turns a module off only when its entry in `modules_statuses.json` is `false`. A module without an entry, and every module of an app without the file, counts as on. laravel-modules does the opposite: its file activator counts a module on only when its entry is `true`. Mortise reads the file the same way. So Larch and Mortise give different results on the shared fixture apps without a statuses file (`defaults`, `custom-folders`, `all-off`). Mortise found this with its shared-fixture test and works around it with `LARCH_COUNTS_UNLISTED_ON`.

## What Changes

- **BREAKING (results):** a module is on only when its entry is `true`. A module without an entry is off, and so is every module of an app without the file. Such an app now gets no break from those modules.
- An entry matches its module as in laravel-modules: by the `name` in the module's `module.json`, the key of that name, else the first key that matches it in any case.
- An empty file holds no entries. A file that is not a JSON object of module names stops the test, as laravel-modules refuses to boot with it. Before, Larch ignored it.
- The shared fixture apps `defaults`, `custom-folders` and `all-off` gain a `modules_statuses.json` that turns every module on, so each `expected.json` keeps its result. In `module-off`, Shipping has no entry instead of `false`.
- Docs: the preset reference and the Mortise guide say how the file is read.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `mortise-preset`: adds the requirement on how the preset reads `modules_statuses.json`.

## Impact

- `src/Mortise/MortisePreset.php`.
- `tests/Feature/MortiseTest.php`, `tests/Fixtures/mortise`.
- `docs/src/content/docs/reference/mortise.md`, `guides/mortise.md`, `CHANGELOG.md`.
- Mortise copies the changed fixture files into `fixtures/larch/` and drops `LARCH_COUNTS_UNLISTED_ON` once Larch releases.
