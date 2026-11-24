---
title: About the baseline
description: Why Larch accepts existing violations, and why an entry names classes instead of lines.
---

## Why a baseline

Rules are easy to write for a new app and hard to add to an old one.
The first run on an existing app finds dozens or hundreds of violations. Fixing them all before the rules can run in CI may take months.
Meanwhile, new violations keep coming.

A baseline splits the problem in two. It records today's violations as accepted, so CI fails only on new ones.
The debt stops growing at once, and you pay it down at your own pace.

## Why no line numbers

An entry names the class that uses, the class it uses, and the kind of link. It has no file and no line.

A line number changes whenever code above it changes. A baseline keyed on lines would report an old violation as new after any edit to its file, and teams would learn to regenerate the baseline without reading it.
A class and its target change only when the link itself changes.

The cost: when a class gains a second link to the same target, of the same kind, the baseline already accepts it.
Larch trades that blind spot for a baseline that stays quiet until something real changes.

## Why fixed entries are listed

A baseline only helps if it shrinks. When a violation is fixed, its entry would hide the same link if it came back.
So after the tests, Larch lists the entries of passing tests that no violation matches any more, and `--update-larch-baseline` removes them.

## Related

- [Adopt Larch in an existing app](/larch/guides/baseline/)
- [Baseline reference](/larch/reference/baseline/)
