---
title: Subjects
description: What a Larch rule can expect - modules, routes, models, jobs and listeners.
---

A subject is the value given to `expect()`. Each rule takes some subjects, listed in [Module rules](/larch/reference/module-rules/) and [App rules](/larch/reference/app-rules/).

## Modules

```php
use function Nwidart\Larch\module;
use function Nwidart\Larch\modules;
```

`module()` and `modules()` are namespaced functions. Import them with `use function`.

### `module()`

```php
module(string $name): Module
```

One module, by the name it has in [`larch()->modules()`](/larch/reference/configuration/#modules). A name that is not a module fails the rule, and the message lists the modules found.

### `module()->publicApi()`

```php
module('Catalog')->publicApi(): PublicApi
```

The public API of one module. A target of `toOnlyDependOn()`.

### `modules()`

```php
modules(): Modules
```

Every module of the app.

## App

```php
use function Nwidart\Larch\larch;
```

Each method that narrows a subject returns a new subject. Calls chain, and each call adds to the ones before.

### `larch()->routes()`

The routes of the booted app.

| Method | Keeps |
|---|---|
| `prefixed(string ...$prefixes)` | Routes whose URI is a prefix, or starts with a prefix and `/`. `'api'` keeps `api` and `api/orders`, not `apis`. |
| `named(string ...$patterns)` | Routes whose name matches a pattern, such as `'admin.*'`. Routes with no name are left out. |
| `except(string ...$patterns)` | Routes whose URI and name match no pattern, such as `'api/webhooks/*'` or `'admin.login'`. |

### `larch()->models()`

The classes Larch reads that extend `Illuminate\Database\Eloquent\Model` and are not abstract.

| Method | Keeps |
|---|---|
| `except(string ...$classes)` | Models that are not a class given, do not extend one, and match no pattern such as `'App\Legacy\*'`. |
| `usedInMorphs()` | Models that declare `morphOne`, `morphMany` or `morphToMany`, and the models named in `morphedByMany`. |

### `larch()->jobs()`

The classes Larch reads that use the `Illuminate\Foundation\Bus\Dispatchable` trait and are not abstract.

| Method | Keeps |
|---|---|
| `queued()` | Jobs that implement `ShouldQueue`. |

### `larch()->listenersOf()`

```php
larch()->listenersOf(string ...$events)
```

The listeners of the events that match the patterns, such as `'Modules\Billing\Events\*'`, as the booted app registers them.
Takes at least one pattern.

- A wildcard registration counts when a pattern matches it, or matches a class Larch reads that it matches.
- Closure listeners are left out.
