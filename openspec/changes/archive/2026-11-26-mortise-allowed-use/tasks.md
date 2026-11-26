# Tasks

## 1. Read the key

- [x] 1.1 Read `rules.allowed-use` in `MortiseConfig`, with `enabled` true by default, and stop on a wrong value; verify with the `mortise.json` tests in `tests/Feature/MortiseTest.php`

## 2. Check the rule

- [x] 2.1 Run `onlyDependOn()` for each module with a list in `MortisePreset`, under the name `allowed-use`, skipping names that are no module; verify with the shared fixture test
- [x] 2.2 Add the shared fixture app `tests/Fixtures/mortise/allowed-use` with `expected.json`, add `allowed-use` to each `expected.json`, and describe both in `tests/Fixtures/mortise/README.md`; verify `composer test` passes
- [x] 2.3 Show the `allowed-use` part of the failure message in the preset run test; verify `composer test` passes

## 3. Docs and release notes

- [x] 3.1 Name the third rule in the preset reference, the Mortise guide and the failure messages reference; remove "Mortise reads this format." from the graph export guide (and the same claim in `GraphExport`); verify `npm run build` in `docs` passes
- [x] 3.2 Add the change to `CHANGELOG.md` under Unreleased

## 4. Checks

- [x] 4.1 Run `composer test`, `composer lint`, `composer analyse` and `openspec validate --all`; verify all pass

## Workflow follow-up

- Archive the change with `/opsx:archive` before the pull request merges.
