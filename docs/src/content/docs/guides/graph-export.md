---
title: Export the module graph
description: Export every link between modules as JSON, or as an HTML report, with php artisan larch:graph.
---

`php artisan larch:graph` exports the graph of modules from the running app.
Package discovery registers the command.

## See the graph in a browser

```bash
php artisan larch:graph --html
```

Larch writes `larch-graph.html` at the project root. Open it in a browser. It needs no network.
The report draws the modules and their links, turns each kind of link on and off, and lists every link and unknown link.

To write it somewhere else:

```bash
php artisan larch:graph --html --output=build/graph.html
```

## Feed the graph to another tool

```bash
php artisan larch:graph --json > graph.json
```

JSON is the default, so `--json` is optional. Mortise reads this format.
The fields are in the [graph format reference](/larch/reference/graph-command/#json-format).

## Pass your configuration

Artisan does not read `tests/Pest.php`. Give the configuration as options when it is not the default:

```bash
php artisan larch:graph \
    --modules='App\Modules\*' \
    --paths=app --paths=Modules \
    --public-api=Contracts --public-api=Events
```

## Read the code only

To skip booting the app and read imports only:

```bash
php artisan larch:graph --imports-only
```

## List the links Larch cannot follow

Unknown links, such as `app($class)` or a binding closure with no return type, are in the `unknown` list of the JSON and in the report.
To make Larch follow a binding, see [Let Larch follow your bindings](/larch/guides/runtime-links/#let-larch-follow-your-bindings).

## Related

- [`larch:graph` reference](/larch/reference/graph-command/)
