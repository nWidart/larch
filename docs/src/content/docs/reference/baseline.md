---
title: Baseline
description: The --update-larch-baseline option, the larch-baseline.json format, and what Pest prints about the baseline.
---

## Option

```bash
./vendor/bin/pest --update-larch-baseline
```

Writes the baseline once the tests are done, through a temporary file. A failed write leaves the baseline as it was.

- Replaces the entries of the tests that pass.
- Keeps the entries of the tests that fail, and adds their new violations. A test that fails may stop before a check.
- Works with `--parallel`.

## File

Default path: `larch-baseline.json` at the project root. Change it with [`larch()->baseline()`](/larch/reference/configuration/#baseline).

```json
{
    "version": 1,
    "tests": {
        "Tests\\Arch\\ModulesTest > billing uses only shared": [
            {
                "from": "Modules\\Billing\\Actions\\ChargeOrder",
                "to": "Modules\\Catalog\\Pricing\\PriceEngine",
                "kind": "import"
            }
        ]
    }
}
```

| Field | Value |
|---|---|
| `version` | `1`. |
| `tests` | Each test name, with the entries it accepts. A preset with several rules has entries per rule. |
| `from` | The class that uses. For app rules: the route (methods and URI) or the class. |
| `to` | The class it uses. For app rules: what is missing, such as the middleware, the members or the event, or empty. |
| `kind` | A [link kind](/larch/reference/link-kinds/), or for app rules `middleware`, `policy`, `define`, `queued` or `morph-map`. |

An entry has no file and no line.

A file that is not valid JSON, or not of version `1`, stops the test. The message asks to delete the file and write it again.

## Output after the tests

Without `--parallel`, Pest prints:

- how many violations the baseline accepted;
- the entries of passing tests that no violation matches any more;
- with `--update-larch-baseline`, how many violations it wrote.

With `--parallel`, these lines are missing, because each worker keeps its own count.
