# Design

## Context

`ReferenceCollector` keeps a stack of the named classes it is inside, and records a reference only while the stack is not empty. `afterTraverse` links an unused import to each declared class of the file. `Edge::from` is a class name, `Larch::moduleOf()` maps it to a module by namespace, and every module rule, the baseline (`ModuleLink::fingerprint()`), the failure messages (`Report`) and the export (`GraphExport`) read the source of a link from `Edge::from`.

Mortise (`mortise-core`, `php.rs` `analyze()` and `app/mod.rs`) reads every name of a file, wherever it is, and gives the file to the module whose folder holds it. It skips `*.blade.php` and the tests of a module.

## Goals / Non-Goals

**Goals:**

- Each class that code outside a named class names is a link, with a source that rules, messages, baseline and export can show.
- A source that is a file maps to the module Mortise gives it.

**Non-Goals:**

- Facade calls, container calls and dispatches outside a class (the runtime kinds). The runtime pass keeps reading call sites inside classes only. Mortise reads imports only.
- Making the default paths read module folders that no PSR-4 root covers, such as `Modules/<Name>/routes` under laravel-modules 11. The guide says to name them with `larch()->paths()`.

## Decisions

### The source of a link from code outside a class is the file's path

`Edge::from` holds the path of the file, relative to the project root: the same string as `Edge::file`. Every reader of `from` then works without a new field: the message reads `Modules/Audit/routes/web.php uses Modules\Billing\...`, the fingerprint is `Modules/Audit/routes/web.php > Modules\Billing\... (import)`, and the export puts the path in `links[].from`.

A path cannot be mistaken for a class: it holds a `/` or a `.`, and a class name holds neither. Larch tells them apart by the graph instead: a source that is not a class of the graph is a file.

Alternatives:

- A made-up class name, such as the file's namespace plus its base name: it reads as a class that does not exist, and two files of one folder can share it.
- Linking the module, not a node: a link is a pair of nodes everywhere else (pairs in the baseline, pairs in the export, pairs in `public-surface-only`).
- Attributing the code to the first class of the file: wrong for code beside a class, and impossible for a classless file.

### Code beside a class links from the file too

Mortise counts every name of a file. Larch makes the same rule simple: the source is the innermost named class around the code, or else the file. An anonymous class outside every named class (`return new class extends Migration`) is code outside a class. Code inside an anonymous class inside a named class stays with the named class, as today.

### The module of a file comes from its folder

Larch knows modules by namespace and the files of their classes. For a file, it walks up from the file's folder to the nearest folder that holds a class it reads (in that folder or below). When all classes under that folder belong to one module, the file belongs to it; else to no module. For a laravel-modules app this is `Modules/<Name>`, the folder Mortise uses.

Alternatives:

- The file's `namespace` statement: route files of laravel-modules have none.
- `module.json`: only laravel-modules has it, and Larch's modules are namespaces (`larch()->modules()`).
- Reverse PSR-4: laravel-modules 11 maps `Modules\<Name>\` to `Modules/<Name>/app/`, and route files sit beside `app/`.

The rule is strict on purpose: a folder that holds classes of two modules, or classes outside every module, gives no module. A wrong guess would break a rule nobody broke. A module nested in another module's folder (`Modules\Billing\Tax` under `Modules/Billing/Tax`) gets its own classless files, and the parent's folder then holds two modules, so a classless file directly under the parent belongs to none. Mortise gives it to the parent; this is a known difference, rare in practice.

The map from folder to module is computed once from the graph and cached with the module map.

### Unused imports

An import no code of the file uses links each declared class of the file, as today, so baseline entries keep matching. In a file with no named class, it links the file. "Used" now counts names outside classes too.

### Blade views are skipped

`*.blade.php` is a template, not PHP. Mortise skips it. Larch read it before, but a view holds no class, so it never linked; now it could. The builder skips it.

### Baseline and export

Fingerprints of links from classes are unchanged, so baselines of today keep matching. A link from a file has the path as `from`; moving the file makes the entry stale, as renaming a class does. The export keeps `VERSION = 1`: its shape does not change, `links[].from` is still a string, and no member of the export lists classes for a reader to join it with. The HTML report shows `from` as text.

## Risks / Trade-offs

- [New links fail rules that passed] → CHANGELOG entry; `--update-larch-baseline` accepts them.
- [Default paths miss route files under laravel-modules 11] → the Mortise guide and the define-modules guide show `larch()->paths('app', 'Modules')`.
- [A file in a folder shared by modules gets no module] → documented in the link kinds reference.
