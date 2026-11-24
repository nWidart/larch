---
title: Mortise preset
description: arch()->preset()->mortise(), and how Larch reads mortise.json and modules_statuses.json.
---

```php
arch()->preset()->mortise();
```

Runs the rules of `mortise.json` at the project root, as [Mortise](https://github.com/nwidart/mortise) reads it.
The preset is one Pest test. Each rule has its own part of the failure message and its own entries in the [baseline](/larch/reference/baseline/).

## `mortise.json`

```json
{
    "rules": {
        "no-circular-use": true,
        "public-surface-only": { "enabled": true, "folders": ["Contracts", "Events"] }
    }
}
```

| Key | Type | Default | Rule |
|---|---|---|---|
| `rules.no-circular-use` | boolean | `true` | [`toHaveNoCycles()`](/larch/reference/module-rules/#tohavenocycles) on every module. |
| `rules.public-surface-only.enabled` | boolean | `true` | [`toOnlyUsePublicApis()`](/larch/reference/module-rules/#toonlyusepublicapis) on every module. |
| `rules.public-surface-only.folders` | list of strings | `["Contracts", "Events"]` | The public API of each module, for this rule. |

- A missing file, or a missing key, means the default.
- A key that is there must hold a valid value. `null` is not one.
- A folder matches the first namespace inside a module, in any case. A folder with a backslash, such as `Internal\Api`, matches no class.
- The rules check imports only, as Mortise does. An import that no class of the file uses counts too.

The test stops when Larch cannot read the file, when the file is not valid JSON, or when a key holds a wrong value.
A folder or a broken link at the path also stops it. Mortise uses the defaults then; Larch does not, so CI does not pass on rules nobody wrote.

## `modules_statuses.json`

Larch reads `modules_statuses.json` at the project root, the file of laravel-modules' file activator.
A module set to `false` breaks no rule, and no link to it does. Cycles are found among the modules that are on.

## Shared fixtures

`tests/Fixtures/mortise` in the Larch repository holds small apps with the result each one expects, so Mortise and Larch can check they agree.
