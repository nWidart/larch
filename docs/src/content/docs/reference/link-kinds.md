---
title: Link kinds
description: The kinds of link between classes that Larch finds, and the Edge methods that select them.
---

```php
use Nwidart\Larch\Edge;
```

`Edge` selects the kinds of link a [module rule](/larch/reference/module-rules/#via) checks: `via(Edge::all())`, `via(Edge::imports(), Edge::events())`.

| Method | Kind | Verb in messages | Link |
|---|---|---|---|
| `Edge::imports()` | `import` | uses | A class named in the code. The default. |
| `Edge::facades()` | `facade` | uses | A static call on a facade, to the class bound to the facade's key. |
| `Edge::container()` | `container` | uses | A string key given to the container, to the class bound to the key. |
| `Edge::events()` | `event` | listens to | A listener, to the event it listens to. |
| `Edge::jobs()` | `job` | dispatches | A class that dispatches a job, to the job. |
| `Edge::all()` | all of the above | | |

All kinds but `import` read the booted app.

## `import`

A class named in:

- a type: parameter, return, property;
- `new`, a static call, `::class`;
- an attribute;
- `extends` and `implements`.

An import that no class of the file uses links each class of the file to the imported class.
An import of a namespace that holds classes, such as `use Modules\Billing\Contracts;`, links nothing by itself. A class used through it, such as `Contracts\Ledger`, is linked.
Code outside a class, such as a route file, links nothing.

## `facade`

A static call on a facade, to the class bound to the facade's key. Real-time facades count. Laravel's own facades are skipped.

## `container`

`app('key')`, `resolve('key')`, `App::make('key')` or `$this->app->make('key')` with a string key, to the class bound to the key.
A key that names a class is already an `import`.

Larch reads the binding, and never builds the service:

- `bind('pricing', PriceEngine::class)` links to `PriceEngine`.
- `singleton('stock', fn (): StockLedger => ...)` links to `StockLedger`, the return type of the closure.

## `event`

A listener, to the event it listens to, as the app registers it: `Event::listen()`, event discovery and closures.

A wildcard such as `'App\Billing\Events\*'` links the listener to each class Larch reads that matches it.
A pattern with no namespace before its `*` is skipped.

## `job`

`Job::dispatch()`, `dispatch(new Job)` or `Bus::dispatch(new Job)`, to the job.

## Unknown links

A call Larch cannot follow becomes an unknown link, with its call site. Examples: `app($class)`, a binding closure with no return type.

No rule checks unknown links. [`larch:graph`](/larch/reference/graph-command/) lists them.
