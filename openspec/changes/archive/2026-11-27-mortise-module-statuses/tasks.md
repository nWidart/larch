# Tasks

## 1. Read the statuses file

- [x] 1.1 Count a module on in `MortisePreset` only when its entry is `true`, matching the key as laravel-modules' `findStatusKey` does, and stop on a file that is not a JSON object; verify with the `modules_statuses.json` tests in `tests/Feature/MortiseTest.php`

## 2. Shared fixtures

- [x] 2.1 Add a `modules_statuses.json` with every module on to `defaults`, `custom-folders` and `all-off`; drop the entry of Shipping in `module-off`; describe the file in `tests/Fixtures/mortise/README.md`; verify the shared fixture test passes

## 3. Docs and release notes

- [x] 3.1 Say how the file is read in the preset reference and the Mortise guide; verify `npm run build` in `docs` passes
- [x] 3.2 Add the change to `CHANGELOG.md` under Unreleased

## 4. Checks

- [x] 4.1 Run `composer test`, `composer lint`, `composer analyse` and `openspec validate --all`; verify all pass
