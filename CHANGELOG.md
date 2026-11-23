# Changelog

## Unreleased

### Added

- Graph of links between classes, read from the code with `nikic/php-parser`.
- Configuration through `larch()`: modules by namespace pattern or by name, public API namespaces, folders to read and to skip.
- Baseline in `larch-baseline.json`, written with `--update-larch-baseline`. A test fails only on violations that are not in it, and the run lists the entries that no violation matches any more. `larch()->baseline()` moves the file.
- Pest expectations `toOnlyDependOn()`, `toOnlyUsePublicApis()` and `toHaveNoCycles()`, with failure messages that list each link with its file and line.
