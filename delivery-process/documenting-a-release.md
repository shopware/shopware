# **Documenting a Release in Shopware**

This guide walks you through how to properly document changes in Shopware releases. The goal is to make sure all developer-facing updates and important upgrade changes are logged clearly, structured well, and easy to find.

- Related doc: [Changelog and Release Info Process](../adr/2025-10-28-changelog-release-info-process.md)

## Why This Process Exists
To have a structured and automated workflow:

1. Every notable change is documented at the PR stage.
2. Upgrade-critical changes are clear for external developers.
3. A mix of automated enforcement and manual curation keeps information accurate.
4. Documentation is centralized in GitHub as a Single Source of Truth (SSOT).

## Where to Document Changes

### Changelog (auto-generated)

The complete raw changelog for each release is generated automatically from GitHub when a tag is created.  
It includes every merged PR and commit and is published on the GitHub **Releases** page.  
This changelog is **not curated** and complements the human-maintained `RELEASE_INFO` and `UPGRADE` files.  
It’s primarily for internal engineers, support, and partners.  

Every PR that introduces a significant change must update one or both of these files:

- RELEASE_INFO.md: Tracks new features, API updates, and general improvements.
- UPGRADE.md: Covers breaking changes, migration steps, and any required developer action.

Developers must edit the version-scoped files RELEASE_INFO-6.x.md and UPGRADE-6.x.md directly in the repository.
The legacy bin/console changelog:create command and /changelog/_unreleased folder are deprecated and scheduled for removal.

## How Do I Know Where to Add My Change?
A simple rule of thumb:

- Use `RELEASE_INFO.md` for:
  - Features, API updates, improvements, and non-breaking changes.
  - Example: "Added a new admin UI filter for orders."

- Use `UPGRADE.md` for:
  - Breaking changes, migration steps, and required developer actions.
  - Example: "Deprecated sw-popover, use mt-floating-ui instead."
 
When a deprecation is introduced (e.g., in a minor release), document the alternative and the timeline in RELEASE_INFO.md. When the breaking change takes effect (e.g., in a major release), document it in UPGRADE.md with full migration steps.

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
For storefront / theming changes.
# App System
For changes in the app system.
# Hosting & Configuration
For config and infrastructure related changes.
```

2. `UPGRADE.md` (Breaking Changes & Migration Guides)

Each entry should include:

```
Changes [A] due to [B], so that [C].
Required Actions: [D].
```

- Example:
```
Changes sw-popover due to UI consistency, so that extensions follow a unified component model.
Required Actions: Replace sw-popover with mt-floating-ui.
```

## Markdown Formatting Guidelines

To maintain a consistent structure and reduce merge conflicts, follow these formatting rules when updating RELEASE_INFO.md and UPGRADE.md:

### General Formatting Rules
1. Newlines Before and After Entries
- Every new entry must have a blank line before and after it.
- Example:
```
## Features

- Added support for XYZ functionality.

## API

- Introduced new API endpoint for retrieving order statuses.
```
2. Headings Must Have a Blank Line Above and Below
Example:
```
## Storefront

- Improved checkout performance.
```
3. Use Bullet Points (-) for Entries
- All changes should be written in a bullet list format.
Example:
```
- Fixed an issue where tax calculations were incorrect in certain cases.
```
4. Use Consistent Sentence Structure
- Start with a verb and describe what was added, changed, or removed.
- ✅ Correct:
```- Added a new admin UI filter for orders.
- Fixed an issue where the tax rate was miscalculated.
- Deprecated the `sw-popover` component in favor of `mt-floating-ui`.
```
- ❌ Incorrect:
```
- New admin UI filter for orders.
- Tax rate miscalculation fix.
- The `sw-popover` component has been deprecated.
```
5. Code Formatting
- Use backticks () for inline code and commands.
- Example:
```
- Deprecated `sw-popover`, use `mt-floating-ui` instead.
```
### Example of a Well-Formatted Entry
```
## Features

- Added support for multi-warehouse inventory tracking.

## API

- Introduced `GET /api/v1/orders/status` for fetching order statuses.

## Core

- Refactored product import logic to improve performance.

## UPGRADE.md

### Breaking Changes

- **What changed**: Deprecated `sw-popover`, use `mt-floating-ui` instead.
- **Why**: Improved UI consistency.
- **Impact**: Affects custom admin extensions using `sw-popover`.
- **Required Actions**: Replace `sw-popover` with `mt-floating-ui`.
```

## How This is Made Consistent

1. Every PR must include documentation: If your PR makes a significant change, update RELEASE_INFO.md and/or UPGRADE.md.
2. GitHub Actions will remind contributors to add missing release notes.
3. Engineering Leads, TDMs, and TPMs ensure documentation is included before merging.
4. DevRel & TDMs refine key updates for clarity before publication.

## What Is Automated?

- GitHub Actions: Check for missing entries in `RELEASE_INFO.md` and `UPGRADE.md`.
- Dev Docs Sync: Pull key info from these files into developer documentation and GitHub Releases.

Everything else (writing entries, categorizing updates, migration steps) is done manually by developers and reviewers.

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
2. GitHub Milestones should align with roadmap and release planning.
3. This will be the go-to reference for all release documentation decisions.
