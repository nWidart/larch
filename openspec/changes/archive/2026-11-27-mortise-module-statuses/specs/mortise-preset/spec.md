## ADDED Requirements

### Requirement: The preset reads modules_statuses.json as laravel-modules does
The preset SHALL read `modules_statuses.json` at the project root. A module SHALL be on only when its entry is `true`. A module without an entry SHALL be off, and every module SHALL be off when the file is missing or empty. The entry of a module SHALL be the key of its name, else the first key, in file order, that matches its name in any case. A file that is not a JSON object of module names SHALL stop the test. A module that is off SHALL break no rule, and no link to it SHALL break one.

#### Scenario: No statuses file
- **WHEN** the app has no `modules_statuses.json`
- **THEN** every module is off, and the preset finds no break

#### Scenario: A module without an entry
- **WHEN** the preset runs on `tests/Fixtures/mortise/module-off`, where Shared is `false` and Shipping has no entry
- **THEN** Shared and Shipping are both off, and the preset finds no cycle

#### Scenario: An entry in another case
- **WHEN** the file holds `{"checkout": true, "SHIPPING": true}`
- **THEN** Checkout and Shipping are on

#### Scenario: A value other than true
- **WHEN** the entry of Shipping is `1`
- **THEN** Shipping is off

#### Scenario: A list in place of the object
- **WHEN** the file holds `[true]`
- **THEN** the test stops and the message names `modules_statuses.json`
