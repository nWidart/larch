---
title: Run Mortise rules in CI
description: Check the boundary rules that Mortise keeps in mortise.json, in a Pest test.
---

[Mortise](https://github.com/nwidart/mortise), the desktop app for laravel-modules, keeps its boundary rules in `mortise.json` at the project root.
Larch runs the same rules in Pest, so CI checks what Mortise shows.

## Add the preset

Create one test:

```php title="tests/Arch/MortiseTest.php"
<?php

arch()->preset()->mortise();
```

Run it:

```bash
./vendor/bin/pest tests/Arch/MortiseTest.php
```

The preset is one Pest test. A failure lists the breaks of each rule under its name.

## Change the rules

Edit the rules in Mortise, or edit `mortise.json`:

```json title="mortise.json"
{
    "rules": {
        "no-circular-use": true,
        "public-surface-only": { "enabled": true, "folders": ["Contracts", "Events"] }
    }
}
```

Without the file, both rules are on, with the folders `Contracts` and `Events`.

## Leave a module out

Turn the module off in `modules_statuses.json` at the project root. A module that is off breaks no rule, and no link to it does.

## Related

- [Mortise preset reference](/larch/reference/mortise/)
- [Adopt Larch in an existing app](/larch/guides/baseline/): the preset works with the baseline.
