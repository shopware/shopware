---
title: Administration test annotations
date: 2026-11-05
area: administration
tags: [administration, jest, testing, code-quality, agents]
---

## Context

Agents write most new Administration Jest tests.
They check whether a test can fail, but they derive what it asserts from the code they just wrote.
Such a test pins whatever the code does, including details nobody decided.

A later reader can't tell these tests apart from tests that protect a real decision or a real bug.
So every test looks equally binding: changing an asserted detail feels risky even when nobody chose it,
and deleting a test that guards a fixed bug looks like any other cleanup.

The information existed once, in the pull request and the issue, but it does not travel with the test.
Regulated domains solve this with traceability from every test to a requirement.
Web projects usually rely on the author's intent and on `git blame` instead.
That was enough while people wrote fewer tests, each on purpose.

## Decision

Every new Jest test in the Administration's `src/` carries a doc comment with exactly one of three tags:

- `@regression` for a test that reproduces a bug that happened. It links the bug with `@see`.
- `@behavior` for a test of a decided behavior. It links the decision with `@see`: a GitHub discussion, a comment, or an ADR.
- `@characterization` for everything else: it pins today's behavior, and nobody decided it.

The tag is chosen by the evidence that can be linked, not by judgment.
Without a source a test is a characterization test, which makes the tag decidable for people and agents alike.
The tag also states what may change the test: removing the bug's code path, revisiting the decision, or any intended change.
The term characterization test comes from Michael Feathers, *Working Effectively with Legacy Code*.

An issue link counts as a source for a regression, because the bug report is the evidence.
For a behavior it needs a comment anchor, because opening an issue is not a decision.
Other sources need a `@seeException` with a reason, and ESLint lists every exception as a warning.
This keeps the escape visible instead of pushing unusual sources into `@characterization`.

Every test carries its own tag, but the `@see` and the description may sit on the enclosing `describe`.
Several tests for one bug then don't repeat it, and a test added to that `describe` later still has to claim the tag itself.
`@characterization` can't sit on a `describe`, so a whole block can't be waved through.

The existing tests stay untagged until someone touches them.
They are listed per test in `test-annotation-baseline.json`, not in `eslint-suppressions.json`.
ESLint's suppressions count per file and rule, so one new test would report every old test of its file,
and replacing an old test with a new untagged one would keep the count and pass.
The CI job that guards the other baselines counts this one per test identity, so a moved spec passes and a new test does not.

A pull request that closes an issue of type Bug and changes Administration source code must add a `@regression` test that links the bug.
The `no-regression-test` label skips the check for bugs Jest can't cover, and the job summary names the skipped bug.

The rules are `sw-test-rules/test-annotation` and `sw-test-rules/test-annotation-see-exception`.
The workflow is described in `src/Administration/Resources/app/administration/technical-docs/11-code-quality/03-test-annotations.md`.

## Consequences

- A reader sees why a test exists and whether it may change, without searching the history.
- Most agent-written tests are tagged `@characterization`, which is accurate and lets later changes update them freely.
- `@regression` and `@behavior` cost a link, so reviewers can check the claim in seconds.
- ESLint can't tell whether a linked source really decides what the test asserts, or whether a test belongs to the bug of its `describe`.
  Reviewers check both.
- Renaming a test or a `describe` drops it from the baseline, so it needs a tag.
- The rule covers the Administration's Jest tests only for now.
