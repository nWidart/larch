# Spec Delta

## Purpose

Which `import` links Larch reads from the code of an app, what the source of each link is, and to which module a source belongs, so that module rules see every place where one module names a class of another.

## ADDED Requirements

### Requirement: Code outside a class links from its file
A class named by code outside every named class SHALL link from the file that holds the code. The source of the link SHALL be the path of the file, relative to the project root. This SHALL hold for a file that declares no class and for code beside a class in a file that declares one. Code inside an anonymous class that no named class holds SHALL count as code outside a class.

#### Scenario: A route file
- **WHEN** `Modules/Audit/routes/web.php` declares no class and registers a route with `InvoiceController::class`, imported from `Modules\Billing\Http\Controllers`
- **THEN** Larch links `Modules/Audit/routes/web.php` to `Modules\Billing\Http\Controllers\InvoiceController`, at the line of the route

#### Scenario: Code beside a class
- **WHEN** a file declares a class and, outside it, calls `new` on a class of another module
- **THEN** the link to that class comes from the file, not from the class

#### Scenario: A migration that returns an anonymous class
- **WHEN** a file returns `new class extends Migration` and the anonymous class names a model
- **THEN** Larch links the file to the model

### Requirement: An unused import of a classless file links the file
An import that no code of a file that declares no class uses SHALL link the file to the imported class, at the line of the import. In a file that declares classes, an unused import SHALL keep linking each class of the file. An import of a namespace that holds classes SHALL link nothing by itself.

#### Scenario: An import never used
- **WHEN** a route file imports a class of another module and never names it again
- **THEN** Larch links the route file to the class, at the line of the import

### Requirement: A file belongs to the module whose folder holds it
The source of a link from a file SHALL belong to a module by the file's place: the nearest folder above the file that holds a class Larch reads. When every class under that folder belongs to one module, the file SHALL belong to that module. Otherwise the file SHALL belong to no module, and its links SHALL break no module rule.

#### Scenario: A route file of a laravel-modules app
- **WHEN** the classes of Audit are under `Modules/Audit/app` and the route file is `Modules/Audit/routes/web.php`
- **THEN** the route file belongs to Audit

#### Scenario: A route file of the app
- **WHEN** `routes/web.php` sits at the project root, which holds classes of several modules and classes outside every module
- **THEN** the route file belongs to no module, and its links break no module rule

### Requirement: Messages, baseline and export name the file
Where a failure message, a baseline entry, `larch:graph` or the graph export names the class that uses, a link from a file SHALL name the path of the file in its place. A baseline entry written before this change SHALL keep matching the same link. The graph export SHALL keep its version.

#### Scenario: A failure message
- **WHEN** `toOnlyDependOn()` fails on the route file of Audit that uses a class of Billing
- **THEN** the message reads `Modules/Audit/routes/web.php uses Modules\Billing\Http\Controllers\InvoiceController`

#### Scenario: A baseline entry
- **WHEN** a baseline is written for that link
- **THEN** its entry has `from` set to `Modules/Audit/routes/web.php`

### Requirement: Blade views are not read as PHP
A file whose name ends in `.blade.php` SHALL NOT be read, as Mortise skips it.

#### Scenario: A view in a module
- **WHEN** a module holds `resources/views/index.blade.php`
- **THEN** Larch does not parse it and finds no link in it

### Requirement: The preset agrees with Mortise on route files
`arch()->preset()->mortise()` SHALL count the links from files of a module as Mortise counts them, in each of its rules.

#### Scenario: The shared fixture app allowed-use
- **WHEN** the preset runs on `tests/Fixtures/mortise/allowed-use`, where the route file `Modules/Audit/routes/web.php` registers a route with `InvoiceController`, an internal class of Billing
- **THEN** the one break of `allowed-use` is Audit using Billing, in 3 files, and the one break of `public-surface-only` is the route file using `Modules\Billing\Http\Controllers\InvoiceController`
