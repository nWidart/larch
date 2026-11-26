# Tasks

## 1. Graph

- [x] 1.1 Record names outside every named class with the file's path as the source in `ReferenceCollector`, link an unused import of a classless file to the file, and skip `*.blade.php` in `GraphBuilder`; verify with `tests/Unit/GraphBuilderTest.php`, replacing the "code outside classes" assertion
- [x] 1.2 Map a file source to the module whose folder holds it in `Larch::links()`; verify with `tests/Unit/LarchTest.php` (a file at the root of the shop fixture belongs to no module) and the preset message test in `tests/Feature/MortiseTest.php` (the message names the route file)

## 2. Shared fixture

- [x] 2.1 Add `Modules/Audit/routes/web.php` and `Modules/Billing/app/Http/Controllers/InvoiceController.php` to `tests/Fixtures/mortise/allowed-use`, update its `expected.json` and `tests/Fixtures/mortise/README.md` (a `from` may be a file path); verify the shared fixture test in `tests/Feature/MortiseTest.php` passes

## 3. Docs and release notes

- [x] 3.1 Remove the gap notes from `reference/mortise.md` and `reference/link-kinds.md`; describe file sources in `reference/link-kinds.md`, `reference/baseline.md`, `reference/failure-messages.md`, `reference/graph-command.md`, `concepts/reading-the-app.md`, `guides/define-modules.md` and `guides/mortise.md`; verify `npm run build` in `docs` passes
- [x] 3.2 Add the change to `CHANGELOG.md` under Unreleased

## 4. Checks

- [x] 4.1 Run `composer test`, `composer lint`, `composer analyse` and `openspec validate --all`; verify all pass

## Workflow follow-up

- Archive the change with `/opsx:archive` before the pull request merges.
