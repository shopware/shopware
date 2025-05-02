# **Documenting a Release in Shopware**

This guide walks you through how to properly document changes in Shopware releases. The goal is to make sure all developer-facing updates and important upgrade changes are logged clearly, structured well, and easy to find.

## Why This Process Exists

We’re moving away from inconsistent and manual-heavy documentation. With this new workflow:

1. Developers must document every notable change directly in the PR.
2. Compliance is automatically enforced, while DevRel and TDMs curate key entries for clarity.
3. We maintain one single source of truth (SSOT) in GitHub and
4. Reduce redundancy and align with internal delivery workflows.

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

Developers should manually edit the RELEASE_INFO.md and UPGRADE.md files directly. The changelog:generate command is deprecated and will be removed.

## How Do I Know Where to Add My Change?
A simple rule of thumb:

- Use `RELEASE_INFO.md` for:
  * New or major reworked user facing features
  * improvements / new developer features 
    This does include:
    - Added extension points
    - new/changed best practices / guidelines
    - Quality of life improvements for other developers

    This does not include:
    - Refactorings of internal code
    - “under the hood” improvements that are backwards compatible

  * deprecations we made
  * everything else we changed that developers should be aware of
  * Critical bugs (not every bug, therefore we have the complete changelog, but critical ones, esp when we do a patch release because of them should be documented)
  * The release notes should describe why we made a change and why external developers should care; it is not about what you changed.
  * Use the RELEASE_INFO file for the version where we made the change, e.g. for a change that is released with 6.7.1.0 put the release notes under the 6.7.1.0 section of the RELEASE_INFO-6.7.md file.

- Use `UPGRADE.md` for:
  - Breaking changes, migration steps, and required developer actions.
  - for every entry include:
    * What we changed
    * Why we changed it, the benefit of the change
    * Why and when externals need to care
    * How they can/need to adjust
  - Use the UPGRADE.md file for the version where the breaking change will happen, e.g for a change that is added in 6.7.1.0, that will lead to a break in 6.8.0.0 use the UPGRADE-6.8.md file.
 
When a deprecation is introduced (e.g., in a minor release), document the alternative and the timeline in RELEASE_INFO.md. When the breaking change takes effect (e.g., in a major release), document it in UPGRADE.md with full migration steps.

## Content Structure

All documented changes should follow this structured format, that is similiar for RELEASE_INFO.md and UPGRADE.md:

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

## Markdown Formatting Guidelines/

To maintain a consistent structure and reduce merge conflicts, follow these formatting rules when updating RELEASE_INFO.md and UPGRADE.md:

### General Formatting Rules
1. Newlines Before and After Entries
- Every new entry must have a blank line before and after it.
- Example:
```
## Features

### New Feature
...

## API

### New API endpoint for retrieving order statuses
...
```
2. Headings Must Have a Blank Line Above and Below
Example:
```
## Storefront

### Improved checkout performance
...
```
3. Use sub-headings per topic
Example:
```
## Features

### New Payment Method
Added new payment method for credit cards.
```

4. Add example code where it makes sense
Example:
```
## API
### New API endpoint for retrieving order statuses
Added `GET /api/orders/status` to fetch order statuses.
Example usage:
GET /api/orders/status?status=shipped
```

5. Code Formatting
- Use backticks () for inline code and commands.
- Example:
```
Deprecated `sw-popover`, use `mt-floating-ui` instead.
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
