---
name: shopware-release-docs
description: Decide and write Shopware developer-facing release documentation (RELEASE_INFO / UPGRADE / changelog entries). Use when a change may affect extension authors, API consumers, operators, or storefront/theme developers — public APIs, deprecations, removals, configuration, or upgrade steps.
license: MIT
---

# Shopware Release Docs

Document only externally relevant changes.

## Decision

Add release documentation when the change affects third-party developers, extension authors, operators, API consumers, or theme/custom storefront developers.

Skip release documentation for narrow local bug fixes, implementation-only refactors, tests-only changes, and client-specific fixes that do not change an external contract.

## Where To Write

Write into the following files, but only if the decision above applies:

- Developer-facing notes: add a concise entry to `RELEASE_INFO-6.<minor>.md` in the upcoming section and relevant category.
  Keep it short and to the point and avoid describing implementation details.
  The short information should inform third party developers about all relevant changes and how to adopt them.
- `UPGRADE-6.<next-major>.md` answers one question: must existing third-party code or configuration (extension, app, integration, theme, hosting setup) change to keep working after the next major?
  If yes, add an entry describing what to change; this covers breaking changes, removals, deprecations, and also features that change required setup.
  If no, add nothing, however large the change is; a bug fix that restores intended behaviour needs no entry either.
  For deprecations, the old path still works; the entry tells developers what to change before it stops.
  Describe the concrete before and after, and write in past tense, as developers read this only after the next major release.
  A change that takes effect right away belongs in the release notes only; an `UPGRADE` entry would promise a break that already happened.
- Public REST/Admin/Store API route additions or changes: add or update the matching OpenAPI JSON schema under `src/Core/Framework/Api/ApiDefinition/Generator/Schema/<AdminApi|StoreApi>/paths`.

Check what the branch you target already does before writing, the convention differs per line and a cherry-pick carries the wrong one with it:

- `trunk` collects entries in the `(upcoming)` section of `RELEASE_INFO-6.<minor>.md`.
- A release branch such as `6.7.13.1` has no `(upcoming)` section. Add the version heading itself at the top of the file, keeping the descending order, and put the entry under it.
- The 6.6 line does not use `RELEASE_INFO` at all. Add `changelog/_unreleased/<date>-<slug>.md` with `title:` and `issue:` frontmatter followed by a `# Core` style section; a later release commit folds those files into `CHANGELOG.md`.

## What To Write

Write from the outside user's perspective: what changed, who is affected, and what they should do.

State the change and its impact, not the reasoning behind it, the internals of how it works, or the history of the problem.

Describe a security fix as an ordinary behaviour change. Do not frame it as an advisory: no attack path, no list of reachable requests, no "expose", "leak", or "other customers" phrasing. `SECURITY.md` routes vulnerabilities to a private process, and release notes reach shops that have not upgraded yet. One sentence of before and after is enough for a reader to decide to patch.

## Formatting

- Put a blank line before and after every heading, and between paragraphs.
- Put the entry under the matching category heading of the version section you target, and add that heading if it is missing.
- Keep code snippets to the shortest form a developer can copy.
