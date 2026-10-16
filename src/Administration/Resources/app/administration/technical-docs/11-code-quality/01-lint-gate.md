# Lint gate

> Status: Stable

The lint gate is the set of rules that `npm run lint` and `npm run lint:fallow` check in the Administration.
Rules that only live in documents do not hold, so every rule a tool can check is part of the gate.

Existing violations are recorded in baselines.
New code has to follow every rule, and the baselines may only shrink.
The decision behind the gate is in [`adr/2026-10-16-administration-lint-gate.md`](../../../../../../../adr/2026-10-16-administration-lint-gate.md).

## Commands

Run them in `src/Administration/Resources/app/administration`, or through the composer wrappers in the repository root.

| Command | What it does |
|---|---|
| `npm run lint` | ESLint with the gate rules and `eslint-suppressions.json` |
| `npm run lint:fallow` | Fallow with `fallow-baseline.json`, finds unused files, exports and dependencies |
| `npm run lint:all` | Types, ESLint, Fallow, Stylelint and Prettier (`composer eslint:admin`) |
| `npm run lint:fix` | ESLint autofix, removes suppressions that are no longer needed (`composer eslint:admin:fix`) |
| `npm run lint:fallow:prune` | Removes Fallow baseline entries that are fixed, never adds one |
| `npm run lint:baseline:init` | Creates `eslint-suppressions.json` for a folder that has none yet |

## Rules

| Group | Rules | Why |
|---|---|---|
| Errors | `sw-core-rules/no-swallowed-errors` | A `.catch()` that drops the error needs a comment that says why |
| Tests | `sw-test-rules/no-vm-access`, spec files (500 lines) | Tests assert what a component renders, emits or requests |
| Unused code | Fallow, configured in `.fallowrc.json` | Unused files, exports and dependencies are removed instead of copied |

`sw-test-rules/no-vm-access` allows `.vm.$emit()`, which is how Vue Test Utils simulates an event from a child or stub.

## Inline disables

Some rules can't be disabled inline.
They are listed in `rulesWithoutInlineDisable` in `eslint.config.ts`.
For these rules the baseline is the only way around, and the baseline can't grow.

Three checks block the other ways:

| Comment | Blocked by |
|---|---|
| `eslint-disable`, `eslint-disable-line` or `eslint-disable-next-line` naming a listed rule, or a bare `eslint-disable` without rule names | `@eslint-community/eslint-comments/no-restricted-disable` |
| `/* eslint <rule>: "off" */`, which changes a rule's config for the file | `@eslint-community/eslint-comments/no-use`, for every rule |
| An `eslint-disable` comment that names an `eslint-comments/` rule | The CI step "Lint directives that switch off the lint gate", which runs `.github/bin/js/check-lint-directives.ts` |

ESLint can't report the last one itself, because such a comment also disables the report about it.
The CI step reads the files instead of running ESLint, so no comment turns it off.
Run it locally from the repository root with `node .github/bin/js/check-lint-directives.ts src/Administration/Resources/app/administration src/Storefront/Resources/app/administration`.

A rule that joins the list brings its existing inline disables into the baseline as `no-restricted-disable` violations.

## Baselines

| Baseline | Covers |
|---|---|
| `src/Administration/Resources/app/administration/eslint-suppressions.json` | ESLint in the Administration |
| `src/Administration/Resources/app/administration/fallow-baseline.json` | Fallow in the Administration |
| `src/Storefront/Resources/app/administration/eslint-suppressions.json` | ESLint in the Storefront's Administration modules |

`eslint-suppressions.json` counts the violations per file and rule.
A file passes while it has at most as many violations of a rule as recorded.
One more, even in a line you did not touch, fails the lint.

The CI job "Lint baselines do not grow" compares the totals per rule (ESLint) and per issue category (Fallow) with the base branch.
It fails when a total grows, or when a baseline still holds entries of a deleted file.
Shrinking needs no review, so the baselines have no code owner.

A rule that joins the gate brings its existing violations into the baseline.
The job lets them in only when the same pull request changes `eslint.config.ts` or `.fallowrc.json`, which need a review from the framework team.

Never regenerate a baseline with `eslint --suppress-all` or `fallow dead-code --save-baseline`.
That would record every new violation as an old one.
`npm run lint:baseline:init` refuses to run when a baseline exists.

## When the gate fails

### A rule fires in a file you changed

Fix the violation.
If the file already has baselined violations of that rule, your change added one more.

### You fixed a violation

ESLint fails with "There are suppressions left that do not occur anymore".
Run `npm run lint:fix` and commit the smaller `eslint-suppressions.json`.

Fallow fails with "Baseline gate failed: ... matched no current issue".
Run `npm run lint:fallow:prune` and commit the smaller `fallow-baseline.json`.

### You moved or renamed a file

The baseline stores paths, so the violations of the moved file count as new.
Fix them if you can.

If you can't fix them in the same change, move the entries:

1. Run `npm run lint:fix`, which removes the entries of the old path.
2. Run `npx eslint <new path> --suppress-rule <rule>` for each rule that still fires.

The totals stay the same, so the growth check passes.

Fallow findings have no per-rule command.
Run `npm run lint:fallow:prune`, then `npx fallow dead-code --save-baseline fallow-baseline.json` as the one exception to the rule above.
Check in the diff that the baseline only swaps the old paths for the new ones.

### You deleted a file

Run `npm run lint:fix` and `npm run lint:fallow:prune`.
The growth check fails as long as `eslint-suppressions.json` holds entries of the deleted file, and `npm run lint:fallow` fails as long as `fallow-baseline.json` does.

### You added an export that extensions use

Fallow reports exports that nothing in the Administration imports.
Extensions import from `src/` too, so Fallow can't see their usage.
Tag every export that extensions may use with `@public`, Fallow then skips it:

```ts
/**
 * @public
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function getCurrentInstance(): ComponentInstanceWithSetupContext | null {
```

`@public` only tells Fallow that the export is used outside the Administration.
Fallow reads it only from a `/** */` block, a `// @public` line comment does not count.
`@private` keeps its meaning for backwards compatibility: anything without it is public API.
Files that are public as a whole are listed under `overrides` in `.fallowrc.json`, and everything in `extension-tooling/` is a Fallow entry point.

### A new folder uses the gate

Run `npm run lint:baseline:init` once in the folder, which needs its own `lint` script, and commit the new `eslint-suppressions.json`.
Add it to the "Lint baselines do not grow" job in `.github/workflows/admin.yml`.
