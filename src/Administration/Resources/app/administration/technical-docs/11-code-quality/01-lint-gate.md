# Lint gate

> Status: Stable

Existing ESLint violations in the Administration are recorded in a baseline.
New code has to follow every rule, and the baseline may only shrink.
The decision is in [`adr/2026-10-16-administration-lint-gate.md`](../../../../../../../adr/2026-10-16-administration-lint-gate.md).

## Baselines

| Baseline | Covers |
|---|---|
| `src/Administration/Resources/app/administration/eslint-suppressions.json` | The Administration |
| `src/Storefront/Resources/app/administration/eslint-suppressions.json` | The Storefront's Administration modules |

Each baseline is ESLint's own [bulk suppressions](https://eslint.org/docs/latest/use/suppressions) file.
It counts the violations per file and rule.
A file passes while it has at most as many violations of a rule as recorded.
One more, even in a line you did not touch, fails `npm run lint`.

## Working with the baseline

- A rule fires in a file you changed: fix the violation.
- You fixed a violation or deleted a file: run `npm run lint:fix`, which also removes the entries that are no longer needed, and commit the smaller baseline.
- You moved or renamed a file: the baseline stores paths, so the violations of the moved file count as new.
  Fix them, or run `npm run lint:fix` and then `npx eslint <new path> --suppress-rule <rule>` for each rule that still fires.
  The totals stay the same.
- A folder without a baseline joins the gate: run `npm run lint -- --suppress-all` once in that folder and commit the new `eslint-suppressions.json`.
  Add the file to the "Lint baselines do not grow" job in `.github/workflows/admin.yml`.

Never run `--suppress-all` on a folder that already has a baseline.
It would record every new violation as an old one.

## Growth check

The CI job "Lint baselines do not grow" runs `.github/bin/js/check-baseline-growth.ts` on pull requests and in the merge queue.
It compares the totals per rule with the base commit and fails when a total grows.
Totals, not per-file counts, so that moving a file does not count as a new violation.

It also fails when a baseline still holds entries of a file that no longer exists.
Those entries would let a new violation of the same rule pass in another file.
`npm run lint:fix` removes them.

The job checks `fallow-baseline.json` the same way, with totals per finding category instead of per rule, see [Unused code](02-unused-code.md).
It skips the check for deleted files there, because `npm run lint:fallow` already fails on entries that match nothing.

## How a new rule joins

A pull request that turns on a rule with existing violations adds them to the baseline with `npm run lint -- --suppress-rule <rule>`.
The growth check lets a rule that has no entries on the base commit in only when the same pull request changes `eslint.config.ts` or `extension-tooling/eslint.mjs`.
The core config builds on the extension factory, so a rule turned on there also reaches the baseline.

This is a known gap: any change to one of these files lets any new rule into the baseline.
Reviewers of such a pull request check the diff of `eslint-suppressions.json`.

## Rules

| Rule | Files | Why |
|---|---|---|
| `complexity` (15), `max-lines-per-function` (80), `max-params` (4), `max-nested-callbacks` (3), `max-depth` (4), `max-lines` (500) | Files in `src/` except specs | Small units are easier to read, test and override |
| `vue/max-template-depth` (10) | Templates | Small units are easier to read, test and override |
| `@eslint-community/eslint-comments/require-description`, `@eslint-community/eslint-comments/no-unlimited-disable` | All | Every disable comment names its rules and says why |
| `@typescript-eslint/ban-ts-comment` with `minimumDescriptionLength: 10` | TypeScript | `@ts-expect-error` needs a reason, `@ts-ignore` and `@ts-nocheck` are not allowed |
| `@typescript-eslint/no-restricted-types` for `$TSFixMe` and `$TSFixMeFunction` | TypeScript | Both are `any` under another name |
| `@typescript-eslint/no-non-null-assertion`, `no-restricted-syntax` for `as unknown as` | TypeScript, no tests | Types match the runtime values instead of being cast into shape |
| `sw-core-rules/no-swallowed-errors` | No tests | A `.catch()` that drops the error needs a comment that says why |
| `filename-rules/not-match` | JavaScript files in `src`, specs are excluded | New source files in `src` are TypeScript |
| `sw-core-rules/no-new-mixins` | `src`, specs are excluded | Shared logic goes into a composable in `src/app/composables`, using existing mixins stays allowed |
| `sw-test-rules/no-vm-access` | `*.spec.js`, `*.spec.ts` | Specs assert what a component renders, emits or requests, not its internals through `wrapper.vm` |

`sw-test-rules/no-vm-access` allows `.vm.$emit()`, which is how Vue Test Utils simulates an event from a child or stub.
It still reports `wrapper.vm.$emit()`, because emitting on the mounted component skips its own code.
Where a spec has no other way, an inline disable with a reason is allowed, for example `// eslint-disable-next-line sw-test-rules/no-vm-access -- <reason>`.

## Inline disables

Every `eslint-disable` comment names the rules it turns off and gives a reason after `--`:

```ts
// eslint-disable-next-line @typescript-eslint/no-non-null-assertion -- the route guard only renders this page with an id
const id = this.$route.params.id!;
```

`rulesWithoutInlineDisable` in `eslint.config.ts` lists the rules that can't be disabled with an `eslint-disable` comment.
For these rules the baseline is the only way around.
Rules join it in the pull requests that add them.
`vue/max-template-depth` is not listed, because `eslint-disable` comments have no effect in Twig templates.

Two rules of `@eslint-community/eslint-plugin-eslint-comments` enforce it:

- `no-restricted-disable` reports disable comments that name a listed rule or an `eslint-comments/` rule, and bare `eslint-disable` comments without rule names.
- `no-use` reports rule config comments such as `/* eslint <rule>: "off" */` for every rule.

A block comment that disables `no-restricted-disable` itself also hides its own report, so ESLint can't catch that one.

A rule that joins the list brings its existing inline disables into the baseline as `no-restricted-disable` violations.
