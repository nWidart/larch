---
title: Modules and public APIs
description: Why Larch checks modules rather than classes, what a public API buys, and why its rules have positive names.
---

## Modules, not classes

A large Laravel app grows in parts: billing, catalog, shipping. Each part changes for its own reasons, often with its own team.
The trouble starts when one part reaches into the inside of another. A change in Catalog's pricing code then breaks Billing, and nobody can see why from Catalog.

Larch checks the links between these parts, not between single classes.
A module is a namespace. A link counts when a class of one module uses a class of another.
Links inside a module, to vendor code, or to code outside every module are not Larch's concern: a module is free inside, and its owner decides how it is built.

This is why rules name modules, `module('Billing')`, and not class patterns. The rule says what the architecture is; the namespaces say where each module lives.

## The public API

Splitting an app into modules helps only if modules can still work together. A public API is how.

Each module keeps some namespaces open to the others, by default `Contracts` and `Events`.
Interfaces in `Contracts` say what the module offers. Events in `Events` say what happened in it.
Everything else is internal and may change without notice to other modules.

`toOnlyUsePublicApis()` checks the line between the two. `toOnlyDependOn()` checks which modules may talk at all.
Together they answer two separate questions: *who may Billing call*, and *how may it call them*.

The default is a convention, not a law. Set your own namespaces with `publicApi()`.

## Why cycles matter

When Billing uses Catalog and Catalog uses Billing, the two are one module in practice: neither can change, test or ship alone.
A cycle can also run through several modules, which makes it hard to see by reading code.
`toHaveNoCycles()` finds every cycle, and lists each step of it, so you can choose which link to break.

## Why rules have positive names

Pest lets you negate an expectation with `not->`. Larch rules that forbid something do not use it.
They have positive names instead: `toHaveNoCycles()`, `toIgnoreEventsOf()`.

The reason is the failure message. `not->` replaces the message of the expectation with its own.
A Larch rule's message is the list of links that break it, with their files and lines. That list is the point of the rule, so a rule may not lose it.

## Related

- [Define your modules](/larch/guides/define-modules/)
- [Guard module boundaries](/larch/guides/module-boundaries/)
