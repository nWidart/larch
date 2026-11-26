# Shared fixture apps for the rules of mortise.json

Two programs check the rules of `mortise.json`: Mortise, in Rust, and Larch's preset, `arch()->preset()->mortise()`, in PHP. These apps, with the result each one expects, keep them in agreement. Mortise can read this folder from the Larch repository.

Each folder is an app with laravel-modules' layout:

- `Modules/<Name>/module.json` and `Modules/<Name>/app/...`, namespace `Modules\<Name>\...`.
- `Modules/<Name>/routes/...`, files that declare no class. Each belongs to the module whose folder holds it.
- `mortise.json`, when the app has one. Without it, the defaults apply: `no-circular-use` and `public-surface-only` on, public folders `Contracts` and `Events`, `allowed-use` off.
- `modules_statuses.json`, when the app turns modules off. A module that is off breaks no rule, and no link to it does.
- `expected.json`, the result on imports:

```json
{
    "no-circular-use": [["Checkout", "Shipping"]],
    "public-surface-only": [
        {"from": "Modules\\Billing\\Actions\\ChargeOrder", "to": "Modules\\Catalog\\Pricing\\PriceEngine"}
    ],
    "allowed-use": [
        {"from": "Audit", "to": "Billing", "files": 2}
    ]
}
```

- `no-circular-use`: each cycle as its modules, sorted by name; the cycles sorted by their first module. `null` when the rule is off.
- `public-surface-only`: each pair where a class uses a class of another module outside its public folders, sorted by `from` then `to`. For code outside every class, such as a route file, `from` is the path of the file, relative to the app folder: `Modules/Audit/routes/web.php`. `null` when the rule is off.
- `allowed-use`: each pair of modules where a module with a list uses a module its list does not name, with the number of files of the module that uses, sorted by `from` then `to`. `null` when the rule is off or `mortise.json` has no `allowed-use` key. Warnings, such as a name that is no module, are not in it.

| App | What it covers |
|---|---|
| `defaults` | No `mortise.json`. A cycle between Checkout and Shipping through public classes. Two links to internal classes. |
| `custom-folders` | Other keys in the file are kept. `no-circular-use` is off while Billing and Catalog use each other. The folders are `contracts`, in lower case, and `Money`. |
| `all-off` | The code of `defaults`, with both rules off. |
| `allowed-use` | Modelled on Mortise's `fixtures/layered`. Audit has an empty list and uses Billing in 3 files: the one break. One of the 3 is `Modules/Audit/routes/web.php`, which declares no class and registers a route with `InvoiceController`, an internal class of Billing: the one break of `public-surface-only`. History may use Audit, Billing and Reports, which is no module, and also uses Legacy, which is off. Ingest has no list. Legacy is off, with an empty list, and uses Billing. |
| `module-off` | The code of `defaults`, with Shared and Shipping off: no cycle, and Billing may use Shared. Checkout imports an internal class of Catalog and never uses it. |
