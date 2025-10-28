Title: Replace changelog ADR with "Changelog & Release Info Process"
Date: 2025-10-28
Status: accepted
Authors: Jonas Elfering, Álvaro Thomas

Context
-------
Historically Shopware used a changelog file workflow based on per-change markdown files under /changelog/_unreleased with an automated build step that aggregated them into CHANGELOG.md and UPGRADE.md. While technically useful, this caused friction: duplication, missing high-level developer-facing context, and the release notes living in a separate repo. The team now prefers a curated, in-repo workflow for developer-facing release notes.

Decision
--------
1. The ADR `2020-08-03-implement-new-changelog.md` is superseded and archived under `adr/_superseded/`.
2. We adopt a curated-in-repo model:
   - `RELEASE_INFO-6.<currentMajor>.md` (developer-facing release notes; per-minor sections).
   - `UPGRADE-6.<upcomingMajor>.md` (upgrade instructions / breaking changes).
3. PR authors add developer-facing entries as part of their PRs:
   - Information that benefits external developers -> `RELEASE_INFO-6.X.md` (Upcoming section).
   - Breaks -> `UPGRADE-6.Y.md`.
4. The full exhaustive changelog remains auto-generated from GitHub release notes (commit history / merges) and stays linked from each release for completeness.
5. The PR template is updated to point to this process and provide the checklist guidance. CI will validate that either RELEASE_INFO or UPGRADE was updated when relevant (or that the PR author has a valid reason not to).

Consequences
------------
- The old ADR file is moved to `adr/_superseded/` for history. (See reference.)
- CI and reviewers should ensure PRs follow the new process. Reviewers are explicitly responsible to check developer-facing entries during code review.
- Marketing/Comms will be instructed to pull content from `RELEASE_INFO` and `UPGRADE` only (not the raw changelog).
- Documentation and internal Confluence will link to these in-repo files as the source-of-truth for developer-facing release info.

References
----------
- Superseded ADR: adr/_superseded/2020-08-03-implement-new-changelog.md
- Confluence: Changelog and Release Notes Process (internal)
