---
title: Configuration
description: The methods of larch(), which configure Larch for one test run.
---

```php
use function Nwidart\Larch\larch;
```

`larch()` returns the configuration of the test run. Call it in `tests/Pest.php`.
Each method returns the configuration, so calls chain.

Requirements: PHP 8.3 or later, Pest 4.3 or later. `via()`, `toIgnoreEventsOf()`, the app rules and `larch:graph` also need `laravel/framework`.

## `modules()`

```php
larch()->modules(string|array|null $modules = null)
```

| Argument | Modules |
|---|---|
| `'App\Domain\*'` | Each namespace directly under `App\Domain`, named by that namespace. The pattern must end in `\*`. |
| `['Billing' => 'App\Billing', ...]` | Each name, with its namespace. |
| none | `Modules\*`, when `nwidart/laravel-modules` is installed. Otherwise a rule fails with a message that asks for `modules()`. |

A class belongs to the module with the longest namespace that holds it.

## `publicApi()`

```php
larch()->publicApi(string ...$namespaces)
```

The namespaces, relative to each module, that other modules may use. Matching ignores case, as PHP namespaces do.

Default: `Contracts`, `Events`.

## `paths()`

```php
larch()->paths(string ...$paths)
```

The folders to read, relative to the project root or absolute.

Default: every PSR-4 folder of the project outside `vendor`.

## `exclude()`

```php
larch()->exclude(string ...$folders)
```

Folder names to skip wherever they appear. Replaces the default list.

Default: `vendor`, `node_modules`, `tests`, `Tests`.

## `root()`

```php
larch()->root(string $root)
```

The project root. Paths in failure messages are relative to it.

Default: the folder that holds the `vendor` folder.

## `baseline()`

```php
larch()->baseline(string $path)
```

The [baseline](/larch/reference/baseline/) file, relative to the project root or absolute.

Default: `larch-baseline.json`.

## Subjects

`larch()` also returns the subjects of the app rules: `routes()`, `models()`, `jobs()` and `listenersOf()`.
See [Subjects](/larch/reference/subjects/).
