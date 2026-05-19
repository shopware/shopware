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

Every PR that introduces a significant change must update one or both of these files:

- RELEASE_INFO.md: Tracks new features, API updates, and general improvements.
- UPGRADE.md: Covers breaking changes, migration steps, and any required developer action.

Developers must edit the version-scoped files RELEASE_INFO-6.x.md and UPGRADE-6.x.md directly in the repository.

### What needs to go into RELEASE_INFO.md?
* New or major reworked user facing features
* improvements / new developer features
  * This **does include**:
    * Added extension points
    * new/changed best practices / guidelines
    * Quality of life improvements for other developers
  * This **does not include**:
    * Refactorings of internal code
    * “under the hood” improvements that are backwards compatible
* deprecations we made
* everything else we changed that developers should be aware of
* Critical bugs (not every bug, therefore we have the complete changelog, but critical ones, esp when we do a patch release because of them should be documented)

Remember: The release notes should describe **why** we made a change and **why** external developers should care; it is **not** (primarily) about **what** you changed.

### What needs to go into UPGRADE.md?
* Everything that might cause a break in projects, extensions, integrations etc.
* Especially every break defined by our backwards compatibility promise needs to be documented: https://developer.shopware.com/docs/resources/guidelines/code/backward-compatibility.html#backward-compatibility

### When you do not need to (explicitly) document a change

* Everything not included above (esp. non-critical bug fixes, internal refactorings) does not need to be documented in RELEASE_INFO.md or UPGRADE.md.

Those changes do not need to be explicitly documented in the release notes, as we will generate a complete changelog for each release based on the semantic PR titles.
The complete raw changelog for each release is generated automatically from GitHub when a tag is created.  
It includes every merged PR and commit and is published on the GitHub **Releases** page.  
This changelog is **not curated** and complements the human-maintained `RELEASE_INFO` and `UPGRADE` files.  
It’s primarily for internal engineers, support, and partners.

### Where do deprecations go?
When a deprecation is introduced (e.g., in a minor release), document the alternative and the timeline in RELEASE_INFO.md. When the breaking change takes effect (e.g., in a major release), document it in UPGRADE.md with full migration steps.

## What information do we need to provide per topic?
* What we changed
* Why we changed it, the benefit of the change
* Why and when externals need to care
* How they can/need to adjust

## Content Structure

All documented changes should follow this structured format:

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
