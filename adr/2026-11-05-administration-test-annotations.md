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

New Jest tests in the Administration's `src/` carry a doc comment with exactly one of three tags:

- `@regression` for a test that reproduces a bug that happened. It links the bug with `@see`.
- `@behavior` for a test of a decided behavior. It links the decision with `@see`: a GitHub discussion, a comment, or an ADR.
- `@characterization` for everything else: it pins today's behavior, and nobody decided it.

The tag is chosen by the evidence that can be linked, not by judgment.
Without a source a test is a characterization test, which makes the tag decidable for people and agents alike.
The tag also states what may change the test: removing the bug's code path, revisiting the decision, or any intended change.
The term characterization test comes from Michael Feathers, *Working Effectively with Legacy Code*.

For regressions `@see` points to an issue, or to a pull request when no issue exists.
For behaviors it points to an ADR, a discussion, or a comment in an issue or pull request.
An issue or pull request itself needs a comment link, because opening it is not a decision.
A source outside these takes `@seeException <reason>`.

Tagging is a convention, not a gate.
The Administration `AGENTS.md`, the `shopware-admin-js` skill and the testing guideline point agents to it, and reviewers ask for it.
No lint rule, baseline or CI job requires a tag, and existing tests stay untagged until someone changes them.
The ESLint rule `sw-test-rules/test-annotation` only checks that tags which are present are used correctly:
one tag per test, and a valid `@see` for `@regression` and `@behavior`.

The workflow is described in `src/Administration/Resources/app/administration/technical-docs/11-code-quality/04-test-annotations.md`.

## Consequences

- Agents tag the tests they write, so most new tests say why they exist.
- A reader sees why a tagged test exists and whether it may change, without searching the history.
- Most agent-written tests are tagged `@characterization`, which is accurate and lets later changes update them freely.
- `@regression` and `@behavior` cost a link, so reviewers can check the claim in seconds.
- Nothing stops an untagged test from landing.
  Reviewers or their agents ask for the tag.
- ESLint checks the form of a source, not whether it is about this test.
  Reviewers check that.
- No baseline to maintain: renaming, moving or splitting specs costs nothing.
- If the convention does not stick, the same rule can later require a tag on new tests.
