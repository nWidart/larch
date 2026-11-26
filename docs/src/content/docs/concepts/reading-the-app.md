---
title: How Larch reads your app
description: The two passes that build the graph of links, why imports are not enough in Laravel, and where Larch stops.
---

Larch builds a graph: each class is a node, and each link from one class to another is an edge.
Code outside a class, such as a route file, is a node too: the file, in the module whose folder holds it.
Rules are checks on that graph. It is built in two passes.

## Pass one: the code

Larch parses every PHP file it reads with `nikic/php-parser`, without running it.
It records each class a file names: in types, `new`, static calls, `::class`, attributes, `extends` and `implements`.
A name inside a class links from that class; a name outside every class links from the file.
These are the `import` links.

This is what import-based tools do too, such as Pest's own `arch()`, Deptrac and PHPat.
For plain PHP code, it is enough.

## Why imports are not enough in Laravel

Laravel links classes in ways the code does not name.

- `Stock::reserve()` calls a facade. The class that does the work is bound in a service provider, perhaps in another module.
- `app('pricing')` asks the container for a key. The code names a string, not a class.
- `Event::listen(OrderPaid::class, ReserveStock::class)` links a listener to an event in a provider, not in the listener.
- `ChargeOrder::dispatch()` sends a job. The caller and the job may live in different modules.

Each of these can cross a module boundary while the imports stay clean.
An import-based tool passes; the architecture is broken.

## Pass two: the booted app

So Larch also reads the booted app. During pass one it keeps the call sites it cannot settle alone: facade calls, container calls, dispatches.
In pass two it asks the app where each one goes.

- For a facade or a container key, Larch reads the class the key is bound to.
- For events, it reads the listeners the dispatcher holds, including discovered ones and wildcards.
- For a job, the class is in the call.

Each link keeps how it is made: the facade or key it goes through, and the file and line where that is bound.
A failure message can then point at both ends: the call, and the provider that made it cross a boundary.

## Where Larch stops

Larch never builds a service to find its class. Building one can run code, open connections, or fail in a test environment.
It reads the binding instead: a class name, or the return type of a binding closure.

When it cannot know, it says so. A call such as `app($class)`, or a closure with no return type, becomes an *unknown* link with its place.
No rule checks unknown links, since a rule cannot judge what it cannot see. `larch:graph` lists them, so you can see where the graph has gaps and close them with a return type.

## Which app it reads

Larch reuses the app a Laravel test case has booted, so it sees what your tests see.
With no booted app, it boots `bootstrap/app.php` itself, in the `testing` environment and without a cached configuration, which could be production's.
It restores the environment variables of the process after. When a test boots another app, Larch reads the graph again from that app.

## Related

- [Check links the app makes](/larch/guides/runtime-links/)
- [Link kinds](/larch/reference/link-kinds/)
