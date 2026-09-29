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
- Public REST/Admin/Store API route additions or changes: add or update the matching OpenAPI JSON schema under `src/Core/Framework/Api/ApiDefinition/Generator/Schema/<AdminApi|StoreApi>/paths`.

## What To Write

Write from the outside user's perspective: what changed, who is affected, and what they should do.

State the change and its impact, not the reasoning behind it, the internals of how it works, or the history of the problem.

## Formatting

- Put a blank line before and after every heading, and between paragraphs.
- Put the entry under the matching category heading of the upcoming version section, and add that heading only if the section does not have it yet.
- Never repeat a heading inside one version section: no second `## Features` under the same `# X.Y.Z.P`, and no entry title the section already documents. The `release-info/section` check rejects a repetition the pull request adds.
- Keep code snippets to the shortest form a developer can copy.
