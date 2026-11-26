# Design

## Context

`MortiseConfig` reads `mortise.json` and stops on a wrong value. `MortisePreset` turns each rule that is on into a `Result`, by its name in `mortise.json`. `Rules::onlyDependOn()` is the check behind `toOnlyDependOn()`, and `Rules` already leaves out the links of modules that are off. Mortise's check is in `mortise-core`, `findings.rs` (`Check::AllowedUse`) and `boundary/links.rs` (`outside_allowed`).

## Goals / Non-Goals

**Goals:** the same breaks as Mortise on imports, one shared fixture app that Mortise can copy.

**Non-Goals:** the rule on runtime links (`toOnlyDependOn()->via(Edge::all())` covers it); Mortise's warning on an unknown name.

## Decisions

- **One rule, one part.** The preset runs `onlyDependOn()` once per module with a list and joins the results into one `Result` named `allowed-use`. Its message is the message of each module, in the order of the key. One name keeps the failure message and the baseline in the shape of the two other rules. One part per module (`allowed-use.Audit`) was the alternative; it would give each module its own baseline entries for no gain.
- **Unknown names are skipped.** `onlyDependOn()` stops on a module it does not know. The preset passes it only the names that are modules of the app. Mortise reports such a name as a warning; a Pest test passes or fails, and a failure would make Larch stricter than Mortise.
- **expected.json counts files.** The member `allowed-use` lists each pair of modules that breaks the rule, with the number of files of the module that uses: `{"from": "Audit", "to": "Billing", "files": 2}`. This is the shape of Mortise's finding (module, the module it may not use, files), so Mortise's test compares without a class list. `null` when the rule is off or the key is missing.

## Risks / Trade-offs

- [A typing mistake in a name passes in silence in Larch] → Mortise warns on it, in the window and in `mortise check`. The preset reference says so.
