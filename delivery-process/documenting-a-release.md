# **Documenting a Release in Shopware**

This guide walks you through how to properly document changes in Shopware releases. The goal is to make sure all developer-facing updates and important upgrade changes are logged clearly, structured well, and easy to find.

## Why This Process Exists

We’re moving away from inconsistent and manual-heavy documentation. With this new workflow, we:

1. Ensure every notable change is documented at the PR stage.
2. Make upgrade-critical changes clear for external developers.
3. Use a mix of automation and manual curation for accuracy and clarity.
4. Maintain one single source of truth (SSOT) in GitHub.
5. Reduce redundancy and align with ProductOS workflows.

## Why This Process Exists
To have a structured and automated workflow:

1. Every notable change is documented at the PR stage.
2. Upgrade-critical changes are clear for external developers.
3. A mix of automated enforcement and manual curation keeps information accurate.
4. Documentation is centralized in GitHub as a Single Source of Truth (SSOT).

## Where to Document Changes
Every PR that introduces a significant change must update one or both of these files:

- RELEASE_INFO.md: Tracks new features, API updates, and general improvements.
- UPGRADE.md: Covers breaking changes, migration steps, and any required developer action.

## How Do I Know Where to Add My Change?
A simple rule of thumb:

- Use `RELEASE_INFO.md` for:
  - Features, API updates, improvements, and non-breaking changes.
  - Example: "Added a new admin UI filter for orders."

- Use `UPGRADE.md` for:
  - Breaking changes, migration steps, and required developer actions.
  - Example: "Deprecated sw-popover, use mt-floating-ui instead."

## Content Structure

All documented changes should follow this structured format:

1. `RELEASE_INFO.md` (Developer-Facing Changes)

```
# Features
Here we describe all new, changed or improved user facing features.
# API
For changes on the API level.
# Core
For PHP/Backend related changes.
# Administration
For admin changes.
# Storefront
For storefront / theming changes
# App System
For changes in the app system.
# Hosting & Configuration
For config and infrastructure related changes
```

2. `UPGRADE.md` (Breaking Changes & Migration Guides)

Each entry should include:

```
What changed: A clear description of the change.
Why it changed: The reason behind it.
Impact: Who needs to care (developers, merchants, integrators, etc.).
Required Actions: Steps needed to migrate, update, or avoid issues.
```

## How This is Made Consistent

1. Every PR must include documentation: If your PR makes a significant change, update RELEASE_INFO.md and/or UPGRADE.md.
2. GitHub actions will remind contributors to add missing release notes.
3. Engineering Leads, TDMs, and TPMs ensure documentation is included before merging.
4. DevRel & TDMs refine key updates for clarity before publication.

## Publishing & Communication

Once documented, changes get published in multiple places:

- **GitHub Releases:** Pulled directly from `RELEASE_INFO.md`.
- **Developer Documentation:** Updated with key info from `RELEASE_INFO.md` and `UPGRADE.md`.
- **Shopware Changelog (Website):** Shows the most important updates.
- **Merchant-Facing Announcements:** Handled separately by PMs & Marketing.

## Who Owns What?

- **Developers:** Add release notes in their PRs.
- **Reviewers (Eng Leads, TDMs, TPMs):** Ensure docs are included and well-structured.
- **DevRel & TDMs:** Review and refine key updates for clarity.
- **PMs & Marketing:** Handle merchant-facing communication.

## Additional Notes

1. To avoid Merge Conflicts we’ll follow structured guidelines to prevent issues (WIP!).
2. GitHub milestones should align with roadmap and release planning.
3. This will be the go-to reference for all release documentation decisions.
