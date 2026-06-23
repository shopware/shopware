---
title: Tooling Stack Version Strategy
date: 2026-06-23
area: infrastructure
tags: [repository, workflow, ci, requirements, versioning, tooling, node, php]
---

## Context

Shopware depends on a stack of external tooling and runtimes — PHP, Node.js, npm, and
similar — to build and run. Historically the versions we support and test against have not
followed a single, predictable rule, and the situation differs per tool:

- **CI drift.** CI configuration and local tooling reference several different, hardcoded
  versions for the same tool (for Node.js, for example, 20, 22, and 24 are spread across
  GitHub Actions, GitLab CI, `.nvmrc`, and container images). Bumping them is manual and
  inconsistent.
- **Undocumented requirements.** For some of the stack there is no clear, current statement
  of what we require. The Node.js requirement in particular is effectively undocumented,
  and the real requirement has drifted: Storefront Components rely on Vite 8, which requires
  npm 11, which ships with Node 24 — silently breaking an unstated Node 20 expectation.

We need one consistent rule for the whole tooling stack, rather than per-tool, ad-hoc
decisions, so that:

1. Contributors and users do not have to pick or track specific versions by hand.
2. We make a clear, explicit commitment about what we support for the lifetime of a major,
   so existing users are never forced into an unplanned upgrade mid-major.

This ADR records that general policy. Node.js is the motivating example; the concrete CI
change for Node.js is handled separately in
[PR #17660](https://github.com/shopware/shopware/pull/17660) ("ci: use Node LTS in
workflows").

## Decision

The version policy below applies to the whole tooling stack (PHP, Node.js, npm, and
comparable runtimes/tools), not to any single tool.

1. **Minimum support is decided at the start of a major.** When a Shopware major begins, we
   commit to a **minimum** supported version for each part of the tooling stack. This
   commitment holds for the entire lifecycle of that major.

2. **The minimum only changes with a new major.** The committed minimum for a tool is never
   raised within a running major. It is re-evaluated and may be raised only when a **new
   Shopware major** starts. Upstream end-of-life of a version is, on its own, not a reason
   to drop support for it within a major.

3. **Newer versions are supported.** Running a newer version of a tool than the committed
   minimum is explicitly fine and expected. Where a tool has an evergreen release line
   (such as Node.js LTS), the current line is the **recommended** version, so no one has to
   track a specific number.

4. **CI tests the minimum and a current version.** CI runs a current/recommended version as
   the primary version for pull requests and trunk, and **additionally** exercises the
   committed **minimum** version in a **nightly** job. This catches regressions against the
   guaranteed minimum before users do, without slowing down every pull request. (For
   Node.js this means: current LTS primary, minimum — currently Node 20 — nightly.)

5. **Documentation.** The system requirements must state, per tool, the committed minimum
   and the recommended version, and that the minimum is only raised on a new major. This
   gives external developers and users a reliable statement to build on.

## Consequences

### Positive

- **One predictable rule.** A single policy covers the whole stack instead of per-tool,
  ad-hoc decisions.
- **No more arbitrary version bumps.** Tooling tracks a current/recommended version
  (e.g. the active LTS) instead of pinned, hand-maintained numbers.
- **Clear external commitment.** Users get an unambiguous, stable statement of the minimum
  we guarantee for a major and the version we recommend.
- **Early regression detection.** The nightly minimum-version job surfaces incompatibilities
  with the guaranteed floor before users hit them.
- **Predictable support window.** Users are never forced into an unplanned upgrade within a
  major; changes happen at major boundaries.

### Negative

- **Multi-version CI matrix.** We maintain and reason about more than one version per tool
  (current/recommended plus the minimum) rather than a single pinned version.
- **Additional CI cost.** Running the minimum versions nightly adds CI runtime.
- **Docs must stay in sync.** The system requirements need review at each major to reflect
  the new minimums and current recommendations.
