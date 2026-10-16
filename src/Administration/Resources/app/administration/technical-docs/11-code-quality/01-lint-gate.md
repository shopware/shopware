# Lint gate

> Status: Stable

The lint gate is the set of rules that `npm run lint` and `npm run lint:knip` check in the Administration.
Rules that only live in documents do not hold, so every rule a tool can check is part of the gate.

Existing violations are recorded in baselines.
New code has to follow every rule, and the baselines may only shrink.
The decision behind the gate is in [`adr/2026-10-16-administration-lint-gate.md`](../../../../../../../adr/2026-10-16-administration-lint-gate.md).

## Commands

Run them in `src/Administration/Resources/app/administration`, or through the composer wrappers in the repository root.

| Command | What it does |
|---|---|
| `npm run lint` | ESLint with the gate rules and `eslint-suppressions.json` |
| `npm run lint:knip` | knip with `knip-baseline.json`, finds unused files, exports and dependencies |
| `npm run lint:all` | Types, ESLint, knip, Stylelint and Prettier (`composer eslint:admin`) |
| `npm run lint:fix` | ESLint autofix, removes suppressions that are no longer needed (`composer eslint:admin:fix`) |
| `npm run lint:knip:prune` | Removes knip baseline entries that are fixed |
| `npm run lint:baseline:init` | Creates `eslint-suppressions.json` for a folder that has none yet |

## Rules

| Group | Rules | Why |
|---|---|---|
| Errors | `sw-core-rules/no-swallowed-errors` | A `.catch()` that drops the error needs a comment that says why |
| Unused code | knip | Unused files, exports and dependencies are removed instead of copied |

## Baselines

| Baseline | Covers |
|---|---|
| `src/Administration/Resources/app/administration/eslint-suppressions.json` | ESLint in the Administration |
| `src/Administration/Resources/app/administration/knip-baseline.json` | knip in the Administration |

`eslint-suppressions.json` counts the violations per file and rule.
A file passes while it has at most as many violations of a rule as recorded.
One more, even in a line you did not touch, fails the lint.

The CI job "Lint baselines do not grow" compares the totals per rule (ESLint) and per issue type (knip) with the base branch.
It fails when a total grows, or when a baseline still holds entries of a deleted file.
Shrinking needs no review, so the baselines have no code owner.

A rule that joins the gate brings its existing violations into the baseline.
The job lets them in only when the same pull request changes `eslint.config.ts` or `knip.json`, which need a review from the framework team.

Never regenerate a baseline with `eslint --suppress-all` or `KNIP_BASELINE_WRITE=1`.
That would record every new violation as an old one.
`npm run lint:baseline:init` refuses to run when a baseline exists.

## When the gate fails

### A rule fires in a file you changed

Fix the violation.
If the file already has baselined violations of that rule, your change added one more.

### You fixed a violation

ESLint fails with "There are suppressions left that do not occur anymore".
Run `npm run lint:fix` and commit the smaller `eslint-suppressions.json`.

knip fails with "baseline entries are fixed".
Run `npm run lint:knip:prune` and commit the smaller `knip-baseline.json`.

### You moved or renamed a file

The baseline stores paths, so the violations of the moved file count as new.
Fix them if you can.

If you can't fix them in the same change, move the entries:

1. Run `npm run lint:fix`, which removes the entries of the old path.
2. Run `npx eslint <new path> --suppress-rule <rule>` for each rule that still fires.

The totals stay the same, so the growth check passes.

### You deleted a file

Run `npm run lint:fix`.
The growth check fails as long as the baseline holds entries of the deleted file.

### You added an export that extensions use

knip reports exports that nothing in the Administration imports.
Extensions import from `src/` too, so knip can't see their usage.
Tag every export that extensions may use with `@public`, knip then skips it:

```ts
/**
 * @public
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function getCurrentInstance(): ComponentInstanceWithSetupContext | null {
```

`@public` only tells knip that the export is used outside the Administration.
`@private` keeps its meaning for backwards compatibility: anything without it is public API.
Files that are public as a whole are listed under `ignoreFiles` in `knip.json`, and everything in `extension-tooling/` is a knip entry point.

### A new folder uses the gate

Run `npm run lint:baseline:init` once in the folder, which needs its own `lint` script, and commit the new `eslint-suppressions.json`.
Add it to the "Lint baselines do not grow" job in `.github/workflows/admin.yml`.
