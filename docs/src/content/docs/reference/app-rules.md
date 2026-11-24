---
title: App rules
description: The expectations that check the routes, models, jobs and listeners of the booted app.
---

App rules read the booted app. Each one takes one [subject](/larch/reference/subjects/#app).

| Expectation | Subject | Baseline kind |
|---|---|---|
| [`toUseMiddleware()`](#tousemiddleware) | `larch()->routes()` | `middleware` |
| [`toHavePolicy()`](#tohavepolicy) | `larch()->models()` | `policy` |
| [`toDefine()`](#todefine) | `larch()->jobs()` | `define` |
| [`toBeQueued()`](#tobequeued) | `larch()->listenersOf()` | `queued` |
| [`toBeInMorphMap()`](#tobeinmorphmap) | `larch()->models()` | `morph-map` |

## `toUseMiddleware()`

```php
expect(larch()->routes())->toUseMiddleware(string ...$middleware)
```

Fails when a route does not run one of the middleware. Each middleware is one of:

- A name with its parameters: `auth:sanctum`. A name without parameters, such as `auth`, also matches `auth:sanctum`.
- A group: `api`. A group counts only when the route declares it and `withoutMiddleware()` removes none of its members.
- A class.

Each route: its methods and URI, its action, and the middleware it declares.

## `toHavePolicy()`

```php
expect(larch()->models())->toHavePolicy()
```

Fails when Laravel finds no policy for a model, registered with `Gate::policy()` or found by name.

## `toDefine()`

```php
expect(larch()->jobs())->toDefine(string ...$members)
```

Fails when a job does not declare a member in a form the queue reads:

- A property.
- A queue attribute, such as `#[Tries(3)]`, for `tries`, `timeout`, `backoff`, `maxExceptions` and `retryUntil`.
- A method, for `tries`, `backoff` and `retryUntil`.

## `toBeQueued()`

```php
expect(larch()->listenersOf('App\Events\*'))->toBeQueued()
```

Fails when a listener of a matching event:

- does not implement `ShouldQueue`, or
- is registered as an object. Laravel calls such a listener at once, even when it implements `ShouldQueue`.

## `toBeInMorphMap()`

```php
expect(larch()->models()->usedInMorphs())->toBeInMorphMap()
```

Fails when a model has no alias in the morph map set with `Relation::morphMap()` or `Relation::enforceMorphMap()`.
