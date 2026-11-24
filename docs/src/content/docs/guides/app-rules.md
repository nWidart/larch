---
title: Enforce app conventions
description: Rules on the routes, models, jobs and listeners of the booted app.
---

App rules read the booted app, as [runtime links](/larch/guides/runtime-links/) do. They need `laravel/framework`.
Each section below is one goal. Use the ones you need.

```php
use function Nwidart\Larch\larch;
```

## Make API routes require authentication

```php
arch('api routes need auth')
    ->expect(larch()->routes()->prefixed('api')->except('api/webhooks/*', 'api/health'))
    ->toUseMiddleware('auth:sanctum');
```

To select routes by name instead of URI, use `named('admin.*')`.
`toUseMiddleware()` takes a name with its parameters (`auth:sanctum`), a group (`api`) or a class.

## Give every model a policy

```php
use Illuminate\Database\Eloquent\Relations\Pivot;

arch('every model has a policy')
    ->expect(larch()->models()->except(Pivot::class))
    ->toHavePolicy();
```

A policy counts when it is registered with `Gate::policy()` or when Laravel finds it by name.
`except()` takes classes, which also skip the models that extend them, and patterns such as `'App\Legacy\*'`.

## Make queued jobs set retry limits

```php
arch('queued jobs set retry limits')
    ->expect(larch()->jobs()->queued())
    ->toDefine('tries', 'timeout');
```

A job defines a member with a property, a queue attribute such as `#[Tries(3)]`,
or, for `tries`, `backoff` and `retryUntil`, a method.

## Queue the listeners of some events

```php
arch('listeners on billing events are queued')
    ->expect(larch()->listenersOf('Modules\Billing\Events\*'))
    ->toBeQueued();
```

Register a queued listener by its class name. Laravel calls a listener registered as an object at once, even when it implements `ShouldQueue`.

## Keep polymorphic models in the morph map

```php
arch('polymorphic models are in the morph map')
    ->expect(larch()->models()->usedInMorphs())
    ->toBeInMorphMap();
```

## Accept the violations you have today

App rules work with the baseline. See [Adopt Larch in an existing app](/larch/guides/baseline/).

## Related

- [App rules reference](/larch/reference/app-rules/)
- [Subjects reference](/larch/reference/subjects/)
