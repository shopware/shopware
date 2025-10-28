---
title: Replace changelog ADR with "Changelog & Release Info Process"
date: 2025-10-28
area: process
tags: [documentation, release, changelog, release-info, upgrade, developer-relations]
authors: [Álvaro Thomas, Jonas Elfering]
status: accepted
---

## Context

Historically, Shopware used a changelog file workflow based on per-change markdown files under `/changelog/_unreleased` with an automated build step that aggregated them into `CHANGELOG.md` and `UPGRADE.md`.  
While technically useful, this caused friction: duplication, missing high-level developer-facing context, and the release notes living in a separate repository.  
The team now prefers a curated, in-repo workflow for developer-facing release notes.

---

## Decision

1. The ADR `2020-08-03-implement-new-changelog.md` is **superseded** and archived under `adr/_superseded/`.
2. We adopt a **curated in-repo model**:
   - `RELEASE_INFO-6.<currentMajor>.md`: developer-facing release notes with per-minor sections.
   - `UPGRADE-6.<upcomingMajor>.md`: upgrade instructions and breaking changes.
3. PR authors must add developer-facing entries as part of their PRs:
   - Information that benefits external developers → `RELEASE_INFO-6.X.md` (in the “Upcoming” section).
   - Breaking changes → `UPGRADE-6.Y.md`.
4. The full exhaustive changelog will remain **auto-generated** from GitHub release notes (commit history and merges) and linked from each release for completeness.
5. The PR template is updated to reference this process and provide checklist guidance.  
   CI will later validate that either `RELEASE_INFO` or `UPGRADE` was updated when relevant (or explicitly skipped with justification).

---

## Consequences

- The old ADR is preserved in `_superseded` for historical reference.  
- PR reviewers must ensure developer-facing entries are added or explicitly marked unnecessary.  
- Marketing and Comms will pull content **only** from `RELEASE_INFO` and `UPGRADE`, not from the raw changelog.  
- Internal documentation (Confluence, DevRel guides) links to these files as the single source of truth for developer-facing release information.  
- A future CI enhancement will enforce the presence of these updates where required.

---

## References

- Superseded ADR: `adr/_superseded/2020-08-03-implement-new-changelog.md`  
