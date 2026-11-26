# mortise-preset Specification

## Purpose
How `arch()->preset()->mortise()` reads `mortise.json` and checks its rules on imports, so that Larch and Mortise give the same result on the same app. The requirements of `no-circular-use` and `public-surface-only` predate OpenSpec in this repository and are not written here yet.

## Requirements

### Requirement: The preset reads rules.allowed-use
The preset SHALL read an optional `rules.allowed-use` object from `mortise.json`, with `enabled` (a boolean, `true` when absent) and `modules` (an object from a module name to a list of module names, empty when absent). Without the key, or with `enabled` set to `false`, the preset SHALL NOT check the rule. A value of another type SHALL stop the test with a message that names the key.

#### Scenario: No key
- **WHEN** `mortise.json` has no `allowed-use` key
- **THEN** the preset checks the two other rules only, and the result has no `allowed-use` part

#### Scenario: The rule off
- **WHEN** `rules.allowed-use.enabled` is `false`
- **THEN** the preset does not check the rule

#### Scenario: A list in place of the modules object
- **WHEN** `mortise.json` holds `"rules": { "allowed-use": { "modules": ["Audit"] } }`
- **THEN** the test stops and the message names `allowed-use.modules`

### Requirement: Each module with a list uses only the modules of its list
When the rule is on, each import from a module with a list to a module that its list does not name SHALL break the rule `allowed-use`. An empty list SHALL mean that the module may use no module. A module without a list SHALL NOT be checked. An import from or to a module that `modules_statuses.json` turns off SHALL break nothing. The breaks SHALL have their own part of the failure message, under `mortise.json, allowed-use:`, and their own baseline entries.

#### Scenario: The shared fixture app allowed-use
- **WHEN** the preset runs on `tests/Fixtures/mortise/allowed-use`, where Audit has an empty list and imports Billing in 2 files, History may use Audit, Billing and Reports and also imports Legacy, which is off, Ingest has no list, and Legacy is off with an empty list
- **THEN** the one break of `allowed-use` is Audit using Billing, in 2 files

### Requirement: A name in allowed-use that is no module is skipped
A name in `rules.allowed-use` that is no module of the app, as a module with a list or in a list, SHALL NOT stop the test and SHALL NOT break the rule.

#### Scenario: A typing mistake
- **WHEN** the list of History names Reports, which is no module
- **THEN** the test does not stop, and Reports breaks nothing

### Requirement: The preset reads modules_statuses.json as laravel-modules does
The preset SHALL read `modules_statuses.json` at the project root. A module SHALL be on only when its entry is `true`. A module without an entry SHALL be off, and every module SHALL be off when the file is missing or empty. A file that is not a JSON object of module names SHALL stop the test. A module that is off SHALL break no rule, and no link to it SHALL break one.

#### Scenario: No statuses file
- **WHEN** the app has no `modules_statuses.json`
- **THEN** every module is off, and the preset finds no break

#### Scenario: A module without an entry
- **WHEN** the preset runs on `tests/Fixtures/mortise/module-off`, where Shared is `false` and Shipping has no entry
- **THEN** Shared and Shipping are both off, and the preset finds no cycle

#### Scenario: A value other than true
- **WHEN** the entry of Shipping is `1`
- **THEN** Shipping is off

#### Scenario: A list in place of the object
- **WHEN** the file holds `[true]`
- **THEN** the test stops and the message names `modules_statuses.json`

### Requirement: The entry of a module is found by its laravel-modules name
The name of a module SHALL be the `name` in the `module.json` nearest above its files, else the folder of that `module.json`, else the name Larch gives it. The entry of a module SHALL be the key of its name, else the first key, in file order, that matches its name in any case.

#### Scenario: A name in module.json
- **WHEN** the `module.json` of the module whose classes are under `Modules\Checkout` names it `StoreCheckout`, and the file holds `{"Checkout": true}`
- **THEN** the module is off; with `{"StoreCheckout": true}` it is on

#### Scenario: An entry in another case
- **WHEN** the file holds `{"storecheckout": true, "SHIPPING": true}`, and the `module.json` of Checkout names it `StoreCheckout`
- **THEN** Checkout and Shipping are on
