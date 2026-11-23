# Shared fixture apps for the rules of mortise.json

Two programs check the rules of `mortise.json`: Mortise, in Rust, and Larch's preset, `arch()->preset()->mortise()`, in PHP. These apps, with the result each one expects, keep them in agreement. Mortise can read this folder from the Larch repository.

Each folder is an app with laravel-modules' layout:

- `Modules/<Name>/module.json` and `Modules/<Name>/app/...`, namespace `Modules\<Name>\...`.
- `mortise.json`, when the app has one. Without it, the defaults apply: both rules on, public folders `Contracts` and `Events`.
- `expected.json`, the result on imports:

```json
{
    "no-circular-use": [["Checkout", "Shipping"]],
    "public-surface-only": [
        {"from": "Modules\\Billing\\Actions\\ChargeOrder", "to": "Modules\\Catalog\\Pricing\\PriceEngine"}
    ]
}
```

- `no-circular-use`: each cycle as its modules, sorted by name; the cycles sorted by their first module. `null` when the rule is off.
- `public-surface-only`: each pair of classes where a class uses a class of another module outside its public folders, sorted by `from` then `to`. `null` when the rule is off.

| App | What it covers |
|---|---|
| `defaults` | No `mortise.json`. A cycle between Checkout and Shipping through public classes. Two links to internal classes. |
| `custom-folders` | Other keys in the file are kept. `no-circular-use` is off while Billing and Catalog use each other. The folders are `contracts`, in lower case, and `Money`. |
| `all-off` | The code of `defaults`, with both rules off. |
