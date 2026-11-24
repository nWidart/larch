---
title: Adopt Larch in an existing app
description: Accept the violations an app has today with a baseline, so a test fails only on new ones.
---

An existing app breaks rules on its first run. A baseline accepts those violations, so CI fails only on new ones.

## Create the baseline

1. Write your rules. Run them once to see what they find.
2. Write the baseline:

   ```bash
   ./vendor/bin/pest tests/Arch --update-larch-baseline
   ```

   Larch writes `larch-baseline.json` at the project root.
3. Commit `larch-baseline.json`.

From now on, a test fails only on violations that are not in the baseline.
The failure message says how many more are in the baseline.

## Remove fixed violations

After the tests, Larch lists the baseline entries of passing tests that no violation matches any more.
To remove them, run Pest with `--update-larch-baseline` again and commit the file.

## Start over

`--update-larch-baseline` keeps the entries of the tests that fail. To start over, delete the file first:

```bash
rm larch-baseline.json
./vendor/bin/pest tests/Arch --update-larch-baseline
```

## Keep the baseline somewhere else

```php title="tests/Pest.php"
larch()->baseline('tests/Arch/baseline.json');
```

## With parallel tests

`--update-larch-baseline` works with `--parallel`. The lines Larch prints after the tests are missing then, because each worker keeps its own count.

## Related

- [Baseline reference](/larch/reference/baseline/)
- [About the baseline](/larch/concepts/baseline/)
