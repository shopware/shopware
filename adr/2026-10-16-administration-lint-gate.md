---
title: Administration lint gate
date: 2026-10-16
area: administration
tags: [administration, eslint, knip, code-quality, agents]
---

## Context

Agents write a growing share of the Administration code, and they copy whatever they find next to it.
Most of the rules that would stop this only lived in documents, and those rules did not hold.
`AGENTS.md` asked for TypeScript, yet half of the components added in the last year were JavaScript.
The rules that ESLint checked did hold.

Turning such rules on for the whole codebase at once is not possible.
The Administration has more than 15,000 existing violations.

## Decision

Every rule a tool can check becomes part of `npm run lint` or `npm run lint:knip`.
The rules and the workflow around them are described in `src/Administration/Resources/app/administration/technical-docs/11-code-quality/01-lint-gate.md`.

### Baselines

Existing violations are recorded in ESLint's own `eslint-suppressions.json` and in `knip-baseline.json`.
New code has to follow every rule, and the baselines only shrink.
Each pattern gets its own rule ID, so the baseline counts it separately.
The baselines hold no autofixable findings, so `npm run lint:fix` never produces a mass diff.

### The rules

- New code is TypeScript, new mixins are not allowed, and components are wrapped in `wrapComponentConfig()`.
  A patch to `eslint-plugin-vue` makes the Vue rules recognize wrapped components.
- Size and complexity have limits.
  `setup()` gets 150 lines instead of 80, because it wires all composables of a component in one function.
- Escape hatches need a reason: disable comments, `@ts-expect-error`, and `.catch()` handlers that drop the error.
  `as unknown as`, `$TSFixMe` and non-null assertions are reported.
- Tests check what the user sees.
  Smoke tests and `.vm` access are reported, except `.vm.$emit()` to simulate an event from a child.
- knip reports unused files, exports and dependencies.
  Exports that extensions may use carry `@public`, since knip can't see imports from extensions.

### Ways around the rules

The size, complexity and structure rules can't be disabled inline, so the baseline is the only way around them.
Every other rule can be disabled with a reason.

A CI job compares the per-rule totals of the baselines with the base branch.
It fails when a total grows, or when a baseline holds entries of a deleted file.
The baselines have no code owner, because shrinking them needs no review.
The rule configuration, the custom rules and the baseline scripts are owned by `@shopware/product-cc-framework`.

## Consequences

- New code follows the rules from its first commit, without a cleanup of the existing code first.
- Moving or renaming a file resurfaces its baselined violations, because the baseline stores paths.
  The developer fixes them or moves the entries; the totals stay the same.
- Every change that fixes a violation also edits a baseline file.
- Comparing totals allows trading: a fixed violation in one file can pay for a new one of the same rule in another.
  The trade is visible in the diff of the baseline.
- `@public` marks extension-facing exports for knip.
  `@private` keeps its meaning: an export without it is public API.
- The Storefront's Administration modules share the configuration and have their own baseline.
