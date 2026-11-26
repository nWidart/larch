# Proposal

## Why

Mortise added a third rule to `mortise.json`, `rules.allowed-use`: a list of the modules each module may use (Mortise phase 5, R9). `arch()->preset()->mortise()` reads only the two rules of today, so Larch and Mortise give different results on an app that has the key. This is LARCH-1 of Mortise's phase 5 plan.

## What Changes

- The preset reads `rules.allowed-use` (`enabled`, `modules`) from `mortise.json`. A wrong value stops the test, as for the other keys.
- When the key is there and on, the preset checks the rule `allowed-use`: each module with a list uses only the modules of that list, on imports. A module without a list is not checked. A module that is off breaks nothing, and no link to it does.
- The rule has its own part of the failure message and its own baseline entries, under `allowed-use`.
- A name in the key that is no module of the app is skipped. Mortise warns on it; a Pest test has no warning, so Larch says nothing.
- A new shared fixture app, `tests/Fixtures/mortise/allowed-use`, with its `expected.json`. Each `expected.json` gains the member `allowed-use`.
- Docs: the preset reference and guide name the third rule. The graph export guide no longer says that Mortise reads the export.

## Capabilities

### New Capabilities

- `mortise-preset`: how `arch()->preset()->mortise()` reads `mortise.json` and checks its rules. This change adds the requirements of the rule `allowed-use`.

### Modified Capabilities

None. No spec exists yet.

## Impact

- `src/Mortise/MortiseConfig.php`, `src/Mortise/MortisePreset.php`.
- `tests/Feature/MortiseTest.php`, `tests/Fixtures/mortise`.
- `docs/src/content/docs/reference/mortise.md`, `guides/mortise.md`, `guides/graph-export.md`, `reference/failure-messages.md`.
- Mortise task 33.1 copies the new fixture app and its `expected.json`.
