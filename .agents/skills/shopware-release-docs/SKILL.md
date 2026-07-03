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

- Developer-facing notes: add a concise entry to `RELEASE_INFO-6.<minor>.md` in the upcoming section and relevant category.
- Breaking changes, removals, or required migration steps: add the concrete migration path to `UPGRADE-6.<next-major>.md`.
- Public REST/Admin/Store API route additions or changes: add or update the matching OpenAPI JSON schema under `src/Core/Framework/Api/ApiDefinition/Generator/Schema/<AdminApi|StoreApi>/paths`.

Write from the outside user's perspective: what changed, who is affected, and what they should do.
