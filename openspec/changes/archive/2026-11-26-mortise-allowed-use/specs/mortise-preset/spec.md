## ADDED Requirements

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
