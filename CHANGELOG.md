# Changelog

## Unreleased

### Added

- Graph of links between classes, read from the code with `nikic/php-parser`.
- Configuration through `larch()`: modules by namespace pattern or by name, public API namespaces, folders to read and to skip.
- Pest expectations `toOnlyDependOn()`, `toOnlyUsePublicApis()` and `toHaveNoCycles()`, with failure messages that list each link with its file and line.
