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

For regressions `@see` needs to point to an issue or if none exists, a pr.
For behaviors it needs to point to an ADR, a discussion, or a comment in an issue or pr.
An issue or pr itself needs a comment link, because opening it probably is not a decision.
If your source deviates from that rule, you can use `@seeException <reason for deviation>`.

Tests can inherit the `@see` from a `describe` above them so they don't need to reference their source over and over again.

An ESLint rule enforces the tags on new tests.
Existing tests are baselined.

A pull request that closes an issue of type Bug and changes Administration source code must add at least one `@regression` test that links the bug.
The `no-regression-test` label skips the check for bugs Jest can't cover, and the job summary names the skipped bug.

The rules are `sw-test-rules/test-annotation` and `sw-test-rules/test-annotation-see-exception`.
The workflow is described in `src/Administration/Resources/app/administration/technical-docs/11-code-quality/03-test-annotations.md`.

Only Administration Jest tests in Platform are affected by the rule.

## Consequences

- New test cases always need an annotation.
- A reader sees why a test exists and whether it may change, without searching the history.
- Most agent-written tests are tagged `@characterization`, which is accurate and lets later changes update them freely.
- `@regression` and `@behavior` cost a link, so reviewers can check the claim in seconds.
- ESLint barely checks whether a linked source is legit.
  Reviewers or their agents need to validate it themselves.
- Renaming a test or a `describe` drops it from the baseline, so it needs a tag.
