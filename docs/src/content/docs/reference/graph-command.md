---
title: larch:graph
description: The options of php artisan larch:graph, and its JSON format.
---

```bash
php artisan larch:graph [options]
```

Exports the graph of modules of the running app. Package discovery registers the command.

## Options

| Option | Value |
|---|---|
| `--json` | Print the graph as JSON. The default. |
| `--html` | Write the HTML report. |
| `--output=` | The file to write, relative to the project root or absolute. Creates its folder. Default: standard output for JSON, `larch-graph.html` for HTML. |
| `--modules=` | The namespace pattern of the modules, such as `App\Modules\*`. Default: as [`larch()->modules()`](/larch/reference/configuration/#modules). |
| `--paths=*` | A folder to read. Repeat for each folder. Default: as [`larch()->paths()`](/larch/reference/configuration/#paths). |
| `--public-api=*` | A public namespace inside each module. Repeat for each one. Default: `Contracts`, `Events`. |
| `--imports-only` | Read the code only, not the booted app. |

The command does not read `tests/Pest.php`, and does not change the `larch()` of the process.
On an error, it prints the message and exits with `1`.

## HTML report

One file that needs no network. It draws the modules and their links, turns each kind of link on and off, and lists every link and unknown link.

## JSON format

```json
{
    "version": 1,
    "generator": "nwidart/larch",
    "kinds": ["import", "facade", "container", "event", "job"],
    "publicApi": ["Contracts", "Events"],
    "modules": [
        { "name": "Billing", "namespace": "Modules\\Billing", "classes": 12 }
    ],
    "links": [
        {
            "from": "Modules\\Billing\\Actions\\ChargeOrder",
            "to": "Modules\\Inventory\\Internal\\StockLedger",
            "fromModule": "Billing",
            "toModule": "Inventory",
            "kind": "facade",
            "public": false,
            "places": [
                {
                    "file": "Modules/Billing/app/Actions/ChargeOrder.php",
                    "line": 18,
                    "through": "the facade Modules\\Shared\\Facades\\Stock",
                    "boundIn": "Modules/Inventory/app/Providers/InventoryServiceProvider.php:13"
                }
            ]
        }
    ],
    "unknown": [
        {
            "from": "Modules\\Billing\\Actions\\ChargeOrder",
            "module": "Billing",
            "file": "Modules/Billing/app/Actions/ChargeOrder.php",
            "line": 24,
            "reason": "app($class): the container key is computed",
            "boundIn": null
        }
    ]
}
```

| Field | Value |
|---|---|
| `version` | `1`. A change that breaks a reader raises it. |
| `generator` | `nwidart/larch`. |
| `kinds` | The [link kinds](/larch/reference/link-kinds/) read. `["import"]` with `--imports-only`. |
| `publicApi` | The public namespaces inside each module. |
| `modules[]` | Each module: `name`, `namespace`, and `classes`, the number of classes Larch read in it. |
| `links[]` | Each pair of classes in two modules where the first uses the second, once per kind. For [code outside a class](/larch/reference/link-kinds/#code-outside-a-class), `from` is the path of the file, relative to the project root. |
| `links[].public` | Whether `to` is in the public API of `toModule`. |
| `links[].places[]` | Each place of the link: `file`, `line`, the facade or container key it goes `through`, and where that is bound (`boundIn`). Both are `null` for imports. |
| `unknown[]` | Each link Larch cannot follow from a class of a module: the class, its module, its place, the `reason`, and `boundIn` when known. |
