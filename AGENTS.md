# Agents

Larch is a Pest plugin that checks architecture rules for Laravel apps. It builds a graph of the links
between classes (`src/Graph`) and checks module rules on that graph (`src/Rules`, `src/Expectations.php`).

## Commands

- `composer test`: run the Pest suite.
- `composer lint`: check code style with Pint. `composer format` fixes it.
- `composer analyse`: run PHPStan.

Run all three before you commit. CI runs them on PHP 8.3, 8.4 and 8.5.

## Branches and worktrees

Every task runs in its own git worktree on its own branch. This keeps a commit on the branch it belongs to.

- The main checkout (the repository root) stays on `main`. Do not edit, stage or commit there.
  Use it only to `git pull` and to add or remove worktrees.
- Start a task with a new branch in a new worktree, from the latest `origin/main`
  (or from the branch the task names as its base):

  ```
  git fetch origin
  git worktree add -b <type>/<slug> .claude/worktrees/<slug> origin/main
  ```

  A worktree that the harness makes (T3 `workspaceStrategy`, Claude Code `EnterWorktree` or
  `isolation: "worktree"`) is also correct. Work only inside that folder.
- One task, one branch, one worktree. Do not run `git checkout <branch>` or `git switch` in a worktree.
  A new task needs a new worktree.
- Agents that run at the same time each get their own worktree. Two agents never commit in one worktree.
- Before each commit, run `git branch --show-current` and `git rev-parse --show-toplevel`.
  Both must name the task's branch and worktree. If not, stop and move the change; do not commit.
- Stage files by path. Do not use `git add -A`, `git add .` or `git commit -a`.
- Never commit to `main`. A change reaches `main` through a pull request.
- After the pull request merges, remove the worktree (`git worktree remove <path>`) and delete the branch.

## Specs and changes

Larch uses [OpenSpec](https://github.com/Fission-AI/OpenSpec). Specs live in `openspec/specs`.
Proposed changes live in `openspec/changes`.

- A change to behavior that users see (a new rule, a new expectation, a new kind of link, a change to
  configuration or to a failure message) starts as an OpenSpec change: `/opsx:propose` in Claude Code,
  `$openspec-propose` in Codex. Write the proposal before the code.
- Build the change with `/opsx:apply`. Keep `tasks.md` up to date as tasks finish.
- When the pull request is ready to merge, archive the change with `/opsx:archive`. This moves its
  spec deltas into `openspec/specs`, in the same pull request.
- A fix that does not change specified behavior, a refactor, or a change to tooling or docs needs no
  OpenSpec change.
- Run `openspec validate --all` before you commit a change to `openspec/`.

## Tests

- Before adding a test, add the assertion to the test that already builds that fixture, graph or
  failure message. A new test needs a new fixture or a new branch, not a new field.
- Test a rule once, at the layer that owns it. `tests/Unit/GraphBuilderTest.php` owns which links the
  graph finds. `tests/Feature/RulesTest.php` owns the result and the failure message of each rule.
  `tests/Feature/ArchTest.php` proves the `arch()` wiring once; it does not replay the cases of
  `RulesTest.php`. Do not repeat a shared code path once per module of the `shop` fixture or once
  per caller.
- Do not test data the test defines, constants, removed features, Pest or PHP-Parser defaults, or
  test helpers.
- Do not make a symbol public only so a test can reach it.
- Advisory: a pull request that adds more than 400 net test lines should offset them elsewhere or say
  why in its body.
