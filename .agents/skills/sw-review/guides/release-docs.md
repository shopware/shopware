---
guide: release-docs
title: Does the release documentation tell outside developers what they need, in the right place?
personas: [maintainer]
rules:
  - { id: DOCS-001, since: 2026-10-09, source: ".agents/skills/shopware-release-docs/SKILL.md#decision", fixture: "tests/guide/release-docs/ignore" }
  - { id: DOCS-002, since: 2026-10-09, source: ".agents/skills/shopware-release-docs/SKILL.md#where-to-write", fixture: "" }
  - { id: DOCS-003, since: 2026-10-09, source: ".agents/skills/shopware-release-docs/SKILL.md#formatting", fixture: "tests/guide/release-docs/catch" }
  - { id: DOCS-004, since: 2026-10-09, source: ".agents/skills/shopware-release-docs/SKILL.md#where-to-write", fixture: "tests/guide/release-docs/catch" }
  - { id: DOCS-005, since: 2026-10-09, source: ".agents/skills/shopware-release-docs/SKILL.md#what-to-write", fixture: "tests/guide/release-docs/catch" }
  - { id: DOCS-006, since: 2026-10-09, source: ".agents/skills/shopware-release-docs/SKILL.md#where-to-write", fixture: "" }
  - { id: DOCS-007, since: 2026-10-09, source: ".agents/skills/shopware-php-code/SKILL.md#deprecations", fixture: "" }
  - { id: DOCS-008, since: 2026-10-09, source: ".agents/skills/shopware-release-docs/SKILL.md#formatting", fixture: "" }
  - { id: DOCS-009, since: 2026-10-09, source: "delivery-process/documenting-a-release.md#what-needs-to-go-into-the-release_info-file", fixture: "" }
---

## Why this guide exists

`RELEASE_INFO` and `UPGRADE` are what plugin authors, API consumers and operators read before an update; release notes, developer docs and marketing are generated from them. Review comments on these files are the most frequent docs topic (about 190 between April and October 2026): entries for plain bug fixes, entries in sections that were already released, and long texts that explain the reasoning instead of what to do.

## Check

- **DOCS-001** Look for an entry about a bug fix, an internal refactoring, a test-only change or `@internal`/`@private` code. It buries the entries that matter; the generated changelog already lists every PR. Ask to remove it. Rule: [Decision](../../shopware-release-docs/SKILL.md#decision), [When you do not need to document a change](../../../../delivery-process/documenting-a-release.md#when-you-do-not-need-to-explicitly-document-a-change). Example: entries for simple bug fixes were added to RELEASE_INFO again and again and had to be removed in review.
- **DOCS-002** Look at which file the entry is in. Additive and already effective changes belong in RELEASE_INFO; what existing code must change for the next major (breaks, removals, required setup) belongs in UPGRADE. Integrators who read only one file miss the other half. Rule: [Where To Write](../../shopware-release-docs/SKILL.md#where-to-write). Example: an added salutation position column was written into UPGRADE instead of RELEASE_INFO; a removal was written only into RELEASE_INFO and was missing from the 6.8 upgrade guide.
- **DOCS-003** Look at the version heading above each added line. An entry must go into the upcoming section (`# 6.X.Y.0 (upcoming)`); a released section is never edited or reworded. Otherwise the change is announced for a version that never had it, and readers of that release learn nothing. Rule: [Formatting](../../shopware-release-docs/SKILL.md#formatting). Example: an entry was added under the released 6.7.10.0 section instead of the upcoming one, 6.7.13 behaviour was described inside the 6.7.12 section, and a released entry was reworded instead of adding to the current version.
- **DOCS-004** Look at the tense of UPGRADE entries. They are read after the next major is installed, so they describe the change as done, with the concrete before and after ("`Foo::bar()` was removed. Use `Foo::baz()`."). "Will be removed" text reads as still pending. Rule: [Where To Write](../../shopware-release-docs/SKILL.md#where-to-write). Example: UPGRADE-6.8 entries used deprecation wording ("will be removed") instead of describing the change as done; hints for the transition belong in RELEASE_INFO.
- **DOCS-005** Look for reasoning, internals, history or measurements in an entry. Readers need what changed, who is affected and what to do; long text hides that, and claims about speed or behaviour must match the code. Rule: [What To Write](../../shopware-release-docs/SKILL.md#what-to-write). Example: a generated wall of text about the SCSS compiler, a cookie consent entry with details that belong in the docs, and a claim of "under a second" that the measured numbers did not support.
- **DOCS-006** Look at the file name. Entries go to `RELEASE_INFO-6.<current minor line>.md` and `UPGRADE-6.<next major>.md`; the UPGRADE file of an already released major is not edited. Rule: [Where To Write](../../shopware-release-docs/SKILL.md#where-to-write). Example: an entry was added to `UPGRADE-6.7.md` after 6.7.0.0 was released.
- **DOCS-007** Look at changes that affect both REST API clients and PHP extensions. They need one entry under `API` and one under `Core`; a single combined entry is skipped by one of the two audiences. Rule: [PHP code, Deprecations](../../shopware-php-code/SKILL.md#deprecations). Example: none recorded yet.
- **DOCS-008** Look at headings: a missing blank line before or after a heading, a new category heading the section already has, or one heading for two unrelated changes. The renderer glues the heading to the paragraph, and readers cannot find the entry. Rule: [Formatting](../../shopware-release-docs/SKILL.md#formatting). Example: a new heading was added without a blank line before it, and a second `## API` heading was added where the section already had one.
- **DOCS-009** Look for an externally relevant change without an entry: a new config option or DAL flag, a new or changed API response field, a changed default or exception, a new extension point. Operators and plugin authors learn about it only from a bug report. Rule: [What needs to go into the RELEASE_INFO file](../../../../delivery-process/documenting-a-release.md#what-needs-to-go-into-the-release_info-file). Example: a new rate limiter option, a new DAL flag for the unused media search, and new translation options and response fields shipped without RELEASE_INFO entries.

## Do not flag

### CI covers

- Danger `MissingReleaseInfo`: a generic warning on every PR without a change to the current `RELEASE_INFO` file. Report DOCS-009 only when you can name the external effect.
- Danger `DeprecatedChangelogFormat`: fails every `changelog/_unreleased/*.md` file. Do not report it again.
- `release-info/section` (`.github/bin/js/release-info-sections.ts`): lines added outside the section named by the `milestone/X.Y.Z.P` label, and a heading repeated within one version section. It reports nothing without a valid milestone label or with `skip-release-info-check`; report DOCS-003 when the PR shows no milestone label.

### Legitimate patterns

- Older sections edited by a backport PR (`backport-*` label), or a typo fix labelled `skip-release-info-check`.
- Entries under `## Critical Fixes` for critical bugs, and deprecation entries in UPGRADE written in the PR that introduces the deprecation.
- Whether a deprecation or break is documented at all is owned by the bc-php guide (BCPHP-006); this guide owns where and how it is written.

## Severity

- `major`: an entry added to or reworded in a released section, a break or removal missing from UPGRADE, or an externally relevant change without any entry. Integrators upgrade without the information they need.
- `minor`: an entry for a bug fix or internal change, future tense, reasoning, a wrong heading or missing blank line, REST and PHP effects in one entry.
- `blocking`: never; documentation can be fixed after merge.

## Retired

(none yet)
