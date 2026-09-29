---
title: Use four-part Shopware version markers in source code
date: 2026-09-29
area: framework
tags: [feature-flag, deprecation, backwards-compatibility, tooling]
---

## Context

Version-shaped major feature flags are registered as four-part IDs, for example `v6.8.0.0`. Other source markers for the same Shopware release commonly use three parts: `@deprecated tag:v6.8.0`, `@experimental stableVersion:v6.8.0`, and `#[BecomesFinal(version: 'v6.8.0')]`. Developers must remember which spelling a consumer expects, even when the values refer to the same planned release.

This distinction has already caused two separate defects:

* [PR #20953](https://github.com/shopware/shopware/pull/20953) corrected PHP deprecation guards that passed `v6.8.0` to `Feature::triggerDeprecationOrThrow()`. That value was not the registered `v6.8.0.0` flag, so the guard could reject still-available functionality. The PR added a PHPStan check for version-shaped feature IDs.
* [PR #20992](https://github.com/shopware/shopware/pull/20992) corrected an Administration rule condition whose `removedInFeature` was `v6.8.0`. The registered `v6.8.0.0` flag did not remove the legacy condition. Its regression test runs with the actual major flag.

The four-part feature IDs are already used at runtime, in configuration, and in tests. Renaming them would break exact lookups. Aligning the other source markers with those IDs removes the ambiguous spelling without changing registered flags.

## Proposed decision

Use `vX.Y.Z.W` for Shopware version markers in current source code. In particular, a marker for a next-major removal should use the exact registered major feature ID, for example `tag:v6.8.0.0` and `version: 'v6.8.0.0'`. Apply the same four-part syntax to experimental stability targets and other planned Shopware lifecycle versions, even when they are not themselves feature flag IDs. Keep named experimental flags such as `ADMIN_EXTENSION_TOOLING` unchanged.

Migrate existing active markers together with their validators and consumers; then reject new three-part source markers. Do not silently append `.0` when a source marker is read. Static checks of arguments passed to `Feature` APIs remain necessary: a four-part annotation cannot prove that a separate runtime argument is correct.

## Migration inventory

| Part of the codebase | Current three-part use | Required change |
| --- | --- | --- |
| Deprecation annotations in PHP, JavaScript, TypeScript, Twig, SCSS, XML, and other source assets | `@deprecated tag:v6.8.0` and `<deprecated>tag:v6.8.0</deprecated>` | Migrate active Shopware removal tags to four parts. Include tests and configuration files; review human-readable removal-version literals in the same code paths. |
| Experimental annotations | `@experimental stableVersion:v6.8.0` | Migrate stability targets to four parts in Core, Administration, Storefront, Elasticsearch, and tests. The `feature:` name is a separate identifier. |
| BC-change attributes | `version: 'v6.8.0'` on usages of attributes implementing `BCChangeAttribute`, including `ExperimentalReplacement` | Migrate attribute usages across source and fixtures. Update the format example in `BCChangeAttribute` and any descriptions of the version contract. |
| Annotation validation | `AnnotationTagVersionSchema::PLATFORM_DEPRECATION_SCHEMA` requires three parts; `AnnotationTagTester` uses it for deprecations, experimental annotations, and BC-change attributes | Require four parts for Shopware lifecycle markers, update error messages, and retain separate validation for manifest schema versions. Update `AnnotationTagTesterTest` and the repository-wide `AnnotationTagTest`. |
| Version comparison in annotation tests | `AnnotationTagTest::getShopwareVersion()` can synthesize a three-part `.0` fallback | Compare markers against a consistently four-part current Shopware version, including fallback and installed-version inputs, so a marker for an already released build is not treated as future. |
| PHPStan rules | `BCChangeAttributeUsageRule::VERSION_PATTERN` accepts only three parts; `DeprecatedServiceDecoratorPattern::getFeatureFlag()` parses three and appends `.0`; `FeatureFlagVersionRule` documents the three-versus-four distinction | Validate four-part BC versions, read the full deprecation tag in decorator checks, remove the suffix conversion, and update diagnostics, rule tests, and fixtures. Preserve the independent four-part check for `Feature` API arguments. |
| Administration lint rules and tests | `private-feature-declarations` tells developers to write `tag:v6.X.0` | Update examples, diagnostics, and rule tests to four parts. Check other Administration deprecation tooling for assumptions about the tag text. |
| Current coding guidance and developer examples | `coding-guidelines/core/feature-flags.md`, `coding-guidelines/administration/feature-flags-and-deprecations.md`, and Administration technical docs show three-part tags and attributes | Describe one four-part convention and update current examples. Update relevant ADR examples when they are used as present-day guidance; leave historical statements about already released versions intact. |

The inventory includes both production declarations and test data. Negative test cases should continue to contain three-part values to prove that validators reject them. Existing `silentUntil` markers, `it.deprecated()` calls, and registered version-shaped feature IDs already use four parts and need no renaming.

## Boundaries and rollout

The two-part manifest schema marker (`manifest:1.0`) has a different version scheme and is unchanged. Historical Git tags with three parts must remain readable; `AnnotationTagVersionSchema::PLATFORM_VERSION_SCHEMA` and `getPlatformVersionFromGitTag()` therefore keep their legacy input support. Dependency constraints, external product versions, URLs, and historical release documentation are not Shopware source lifecycle markers and should not be mechanically rewritten.

The GitHub workflow action that currently works with three-part versions is outside this migration. It may keep its current format. This ADR does not change the registered feature IDs or their environment-variable names.

Apply the migration in one coordinated change: update validators and consumers, rewrite active annotations and BC attributes, update guidance and fixtures, then run the repository annotation checks, PHPStan, and the affected Administration lint/tests. Enforcing the new syntax before migrating source markers would make the repository checks fail; converting source markers before updating the decorator rule would make that rule look for the wrong flag.

## Consequences

The same planned Shopware release has one spelling wherever it is used as a source lifecycle version. Removing a major flag and its corresponding deprecations no longer requires translating a three-part tag into a four-part ID. The source migration is broad and affects extension-facing annotations, so the implementation needs developer-facing release guidance and a clear example for extension authors. Historic versions and external version schemes remain readable under their existing rules.
