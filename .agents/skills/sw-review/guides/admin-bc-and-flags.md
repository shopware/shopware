---
guide: admin-bc-and-flags
title: Are Administration feature flags and deprecations easy to test, announce and remove?
personas: [architecture, ux]
rules:
  - { id: ADMF-001, since: 2026-10-09, source: "coding-guidelines/administration/feature-flags-and-deprecations.md#feature-flags", origin: "coding-guidelines/administration/feature-flags-and-deprecations.md#feature-flags", fixture: "tests/guide/admin-bc-and-flags/catch" }
  - { id: ADMF-002, since: 2026-10-09, source: "coding-guidelines/administration/feature-flags-and-deprecations.md#feature-flags", origin: "coding-guidelines/administration/feature-flags-and-deprecations.md#feature-flags", fixture: "" }
  - { id: ADMF-003, since: 2026-10-09, source: "coding-guidelines/administration/feature-flags-and-deprecations.md#feature-flags", origin: "coding-guidelines/administration/feature-flags-and-deprecations.md#feature-flags", fixture: "tests/guide/admin-bc-and-flags/catch" }
  - { id: ADMF-004, since: 2026-10-09, source: "coding-guidelines/administration/feature-flags-and-deprecations.md#feature-flags", origin: "https://github.com/shopware/shopware/pull/18552", fixture: "" }
  - { id: ADMF-005, since: 2026-10-09, source: "coding-guidelines/administration/feature-flags-and-deprecations.md#feature-flags", origin: "https://github.com/shopware/shopware/pull/18894", fixture: "" }
  - { id: ADMF-008, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#administration", origin: "https://github.com/shopware/shopware/pull/18970", fixture: "tests/guide/admin-bc-and-flags/catch" }
---

## Why this guide exists

Administration flags and deprecations decide whether extension developers can test against the next major and whether core can delete the legacy path cleanly. Unlike PHP, no PHPStan rule checks flag ids, flag nesting or deprecation guards in Administration code, and two generated lists (`blocks-list.json`, `position-identifiers.json`) are the only record of what extensions may rely on. This guide covers flags, the deletable legacy branch and those two registries; the component API, the deprecation format and runtime guard, the Meteor migration path and intended public breaks belong to the bc-admin guide. Removed entries in those lists were the most frequent Administration BC comment between August and October 2026 (12 comments).

## Check

- **ADMF-001** Look at flag ids in `Feature.isActive()`, `feature.isActive()`, `flag:` and templates. Use the id as registered in `feature.yaml`: major flags lowercase (`v6.8.0.0`), named flags as written (`JSON_LD_DATA`). The env-style `V6_8_0_0` resolves today, but searches for the flag miss it, so it survives the flag's removal. Rule: [Feature flags](../../../../coding-guidelines/administration/feature-flags-and-deprecations.md#feature-flags). Example: no incident recorded yet.
- **ADMF-002** Look for one flag switching several unrelated behaviours. It cannot be enabled for one change without the others, and removing it touches unrelated screens. Rule: [Feature flags](../../../../coding-guidelines/administration/feature-flags-and-deprecations.md#feature-flags). Example: no incident recorded yet.
- **ADMF-003** Look for a flag check inside another flag's branch. Each nesting doubles the states to test, and the inner branch is easily left behind when the outer flag is removed. Combine the condition or separate the branches. Rule: [Feature flags](../../../../coding-guidelines/administration/feature-flags-and-deprecations.md#feature-flags). Example: no incident recorded yet.
- **ADMF-004** Look at where the old behaviour lives. It must sit in the branch that is deleted with the flag (an explicit `else` or an early return when the flag is off), so removing the flag is a pure deletion. Mixed code keeps legacy behaviour alive after the major. Rule: [Feature flags](../../../../coding-guidelines/administration/feature-flags-and-deprecations.md#feature-flags). Example: #18552, #16611.
- **ADMF-005** Look at the Jest spec of flagged code: both supported flag states need a test, without copying identical tests for both. An untested branch breaks unnoticed when the flag flips at the major. Rule: [Feature flags](../../../../coding-guidelines/administration/feature-flags-and-deprecations.md#feature-flags). Example: #18894 (identical tests for both states), #20213 (tests still needed during 6.8).
- **ADMF-008** Look for removed or renamed entries in `blocks-list.json` or `src/meta/position-identifiers.json`. Each entry is a block or position that extensions override or render into; regenerating the list makes the meta test pass, and merchants lose the app or plugin content on that screen without notice. Deprecate instead, and document it in UPGRADE and RELEASE_INFO. Rule: [Administration](../../../../coding-guidelines/core/backward-compatibility.md#administration). Example: #18970, #15271.

## Do not flag

### CI covers

- Jest `src/meta/meta.spec.js`: fails when a template loses a block or position identifier that the lists still contain, or adds one the lists lack. It cannot see a removal that also deletes the list entry; that is ADMF-008.
- ESLint `sw-deprecation-rules/no-deprecated-components`, `no-sw-tabs-usage` and `private-feature-declarations` (core using deprecated components, new exports not `@private`), `sw-test-rules/stabilize-feature-flag` (tests activating shipped flags).

### Legitimate patterns

- Bug fixes and non-breaking changes shipped without a flag (#19668); not every change needs the major flag.
- `@private` members and `_`-prefixed identifiers: no deprecation duty.
- Removal of a flag together with its legacy branch, configuration and tests in one PR.
- Component API breaks (removed props, slots, methods, required props, Twig blocks inside templates), intended public breaks without discussion (BCADM-001), the deprecation tag format and runtime guard (BCADM-006) and the Meteor `mt-*` migration path (BCADM-007) belong to the bc-admin guide.

## Severity

- `blocking`: an entry removed from `blocks-list.json` or `position-identifiers.json` in a minor without deprecation. Extension content vanishes after the update.
- `major`: nested flags, legacy code outside the deletable branch, or a flagged branch without a test.
- `minor`: flag id spelling, one flag covering several behaviours.

## Retired

- ADMF-006 (2026-10-09): duplicate of BCADM-006 in bc-admin.md.
- ADMF-007 (2026-10-09): duplicate of BCADM-006 in bc-admin.md.
- ADMF-009 (2026-10-09): duplicate of BCADM-001 and its `requires_human` severity anchor in bc-admin.md.
- ADMF-010 (2026-10-09): duplicate of BCADM-007 in bc-admin.md.
