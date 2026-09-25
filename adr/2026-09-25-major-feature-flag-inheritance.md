---
title: Major feature flag inheritance
date: 2026-09-25
area: framework
tags: [feature-flag, workflow, major-version]
---

## Context

We need to test the behavior of an upcoming major release without also enabling every unrelated experimental feature. A major version flag and separately toggleable feature flags can describe parts of the same release, but until now there was no relationship between them. Call sites handled this inconsistently: some checked both `v6.8.0.0` and a feature flag such as `CACHE_REWORK`, while others checked only the feature flag, such as `JSON_LD_DATA`. Enabling the major flag alone therefore did not reliably enable the complete 6.8 behavior.

CI compensated with a special `FEATURE_ALL=major` value. That made a global "all features" setting mean different things depending on its value, and put release composition in workflow logic instead of the feature declarations. We also need a third state: alongside the current trunk state and a specific major release state, we still need a run with *all* features enabled. Some flags target a minor release or may remain experimental across major versions; they must be testable without automatically becoming part of the next major.

## Decision

A standalone major flag is identified by its version-shaped name, such as `v6.8.0.0`. A separately toggleable feature that is part of that release declares `major: v6.8.0.0` in its feature configuration. Enabling the major flag makes that sub-feature active by default, but an explicit setting for the sub-feature can still turn it off. Features without a declared parent remain independent.

The precedence for a sub-feature is: its explicit environment value or stored/runtime toggle, then a truthy `FEATURE_ALL`, then an active declared major parent, then its configured default. `FEATURE_ALL` retains one meaning: any truthy value enables all registered features. It no longer has special `major`, `minor`, or version-shaped modes. The `major` configuration field is a parent flag name, not a boolean classification.

## Consequences for developers enabling flags

- `V6_8_0_0=1` opts into the 6.8 release state, including declared 6.8 sub-features such as `CACHE_REWORK` and `JSON_LD_DATA`, but not unrelated experimental flags or features assigned to a later major.
- A specific sub-feature can still be enabled on its own to preview it, or explicitly disabled while its parent major is active. This preserves targeted testing and allows incomplete features to stay out of a major release.
- Developers should declare the parent on a feature intended for a major release and check that feature's own flag at call sites. They no longer need duplicate `major || feature` checks or to rely on CI to assemble the release state. Features intended for a minor release or more than one major cycle should have no parent until they are assigned to a specific major.
- `FEATURE_ALL=1` is for testing all features together, including independent experiments. It is not a shortcut for a specific release state.

## Consequences for workflows

- Major CI enables the version flag directly, for example `V6_8_0_0=1`. The integration, acceptance, and migration lanes no longer need a special `FEATURE_ALL=major` mode or a generated version-lane matrix to activate the associated sub-features.
- An all-features run remains distinct and can use `FEATURE_ALL=1` to cover flags outside the upcoming major. This avoids losing coverage for features that are released during a major cycle or stay behind a flag for longer.
- When the next major changes, the explicit version flag in the few major workflow settings must be updated. The feature configuration remains the source of truth for which sub-features belong to that major. The unit test bootstrap already enables all registered flags independently of `FEATURE_ALL`, so unit tests that need an older state must disable the relevant major flag explicitly.
