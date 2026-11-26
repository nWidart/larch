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
        "public-surface-only": { "enabled": true, "folders": ["Contracts", "Events"] },
        "allowed-use": {
            "enabled": true,
            "modules": { "Audit": [], "History": ["Audit", "Billing"] }
        }
    }
}
```

| Key | Type | Default | Rule |
|---|---|---|---|
| `rules.no-circular-use` | boolean | `true` | [`toHaveNoCycles()`](/larch/reference/module-rules/#tohavenocycles) on every module. |
| `rules.public-surface-only.enabled` | boolean | `true` | [`toOnlyUsePublicApis()`](/larch/reference/module-rules/#toonlyusepublicapis) on every module. |
| `rules.public-surface-only.folders` | list of strings | `["Contracts", "Events"]` | The public API of each module, for this rule. |
| `rules.allowed-use.enabled` | boolean | `true` when `allowed-use` is there | [`toOnlyDependOn()`](/larch/reference/module-rules/#toonlydependon) on each module with a list. Without `allowed-use`, the rule is off. |
| `rules.allowed-use.modules` | object of lists of strings | `{}` | For each module with a list, the modules it may use. |

- A missing file, or a missing key, means the default.
- A key that is there must hold a valid value. `null` is not one.
- A folder matches the first namespace inside a module, in any case. A folder with a backslash, such as `Internal\Api`, matches no class.
- The rules check imports only, as Mortise does. An import that no code of the file uses counts too. An import of a namespace, such as `use Modules\Billing\Contracts;`, counts only where the code uses a class through it.
- Code outside a class, such as a route file, counts for the module whose folder holds the file, as in Mortise. The message names the file in place of a class. See [Code outside a class](/larch/reference/link-kinds/#code-outside-a-class).
- Under `allowed-use`, a module with a list may use only the modules of its list. An empty list means no module. A module without a list is not checked.
- A name under `allowed-use` that is no module of the app breaks nothing. Mortise warns on it; the preset does not, because a Pest test only passes or fails.

The test stops when Larch cannot read the file, when the file is not valid JSON, or when a key holds a wrong value.
A folder or a broken link at the path also stops it. Mortise uses the defaults then; Larch does not, so CI does not pass on rules nobody wrote.

## `modules_statuses.json`

Larch reads `modules_statuses.json` at the project root, the file of laravel-modules' file activator.
It reads the file as laravel-modules does:

- A module is on only when its entry is `true`. A module without an entry is off, and so is every module of an app without the file.
- The name of a module is the `name` in its `module.json`, as laravel-modules boots it, else the folder of that `module.json`. A module without a `module.json` keeps the name Larch gives it.
- The entry of a module is the key of its name, else the first key that matches the name in any case.
- An empty file, or one of JSON whitespace only, holds no entries. A file that is not a JSON object of module names stops the test.

A module that is off breaks no rule, and no link to it does. Cycles are found among the modules that are on.

## Shared fixtures

`tests/Fixtures/mortise` in the Larch repository holds small apps with the result each one expects, so Mortise and Larch can check they agree.
