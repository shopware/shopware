---
guide: bc-removal-before-major
title: Measure before you remove in the major
personas: [maintainer, architecture]
retire_after: "6.8.0.0"
rules:
  - { id: REMOVAL-001, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#when-a-break-is-acceptable", fixture: "tests/guide/bc-removal-before-major/catch" }
  - { id: REMOVAL-002, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#when-a-break-is-acceptable", fixture: "" }
  - { id: REMOVAL-003, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#public-api-is-every-extension-surface", fixture: "" }
  - { id: REMOVAL-004, since: 2026-10-09, source: ".agents/skills/shopware-php-code/SKILL.md#deprecations", fixture: "" }
---

## Why this guide exists

The 6.8 Stability Track measured the queued removals against a corpus of extensions. Several deprecated surfaces turned out to be used widely: `$tc` in 39% of plugins, the cart-alerts template in 12%, `sw-empty-state` in 11%. Each sub-issue has the acceptance criterion "impact drops to 0%", reached by a compatibility path or by migrating the ecosystem first. This guide retires once 6.8.0.0 ships; REMOVAL-004 applies to every major and moves to a flags guide then.

## Check

- **REMOVAL-001** Look for the removal of a deprecated public surface in the major (code gated by the `v6.8.0.0` flag, the `shopware.inactiveFeature` tag, an entry in `CompatTwigExtension`, a deleted deprecated symbol) and ask for its measured impact: the share of extensions that use it. Without that number nobody can weigh the upgrade work of plugin authors against the benefit, and unknown usage is not zero usage. Rule: [backward-compatibility.md#when-a-break-is-acceptable](../../../../coding-guidelines/core/backward-compatibility.md#when-a-break-is-acceptable). Example: the planned removal of `$tc` in the Administration was measured at 39% of plugins.
- **REMOVAL-002** When the impact is significant or unknown, ask whether a forwarding shim or compatibility path avoids the break: keep the old name and delegate, keep the field and map it, keep a forwarding template. A shim usually costs core a few lines and saves every affected extension a release. Rule: [backward-compatibility.md#when-a-break-is-acceptable](../../../../coding-guidelines/core/backward-compatibility.md#when-a-break-is-acceptable). Example: the `theme.json` translations were kept as fields and mapped to the new format instead of being removed.
- **REMOVAL-003** Look at removals outside PHP signatures: Twig functions, globals and template variables, JS plugin options and helpers, Administration components, theme.json and manifest fields. The BC check only sees PHP, so these breaks pass CI silently and surface as errors in merchants' shops after the upgrade. Rule: [backward-compatibility.md#public-api-is-every-extension-surface](../../../../coding-guidelines/core/backward-compatibility.md#public-api-is-every-extension-surface). Example: controller template variables queued for removal were used by about 9% of plugins, and the `category_url` and `category_linknewtab` Twig functions by about 6%.
- **REMOVAL-004** Look for code that exists only until the next major but is neither marked nor gated: a bridge, legacy branch or helper without `@deprecated tag:vX.Y.0` or an inline `// @deprecated tag:` comment, behaviour that must be gone in the major without a `Feature::isActive()` branch, a DAL field that UPGRADE calls removed but that has no `Deprecated` flag and no next-major migration. The cleanup at the major misses it, and the legacy code survives another cycle. Mark or gate it in the PR that adds it. Rule: [Deprecations](../../shopware-php-code/SKILL.md#deprecations). Example: temporary state machine code that must go with 6.8 had no deprecation comment, and the deprecated `availableStock` product field had to be gated so it is already missing with the 6.8 flag. UPGRADE called theme config fields removed that were still exposed without a `Deprecated` flag.

## Do not flag

CI covers:

- `bc-checker` job (Roave BC check) for PHP signatures; `DeprecatedMethodsThrowDeprecationRule`, `DeprecatedServiceFeatureTagRule` and `DeprecatedServiceDefinitionFeatureTagRule` (PHPStan) for the deprecation signal, the `shopware.inactiveFeature` tag and the `CompatTwigExtension` map; `FeatureFlagVersionRule` for flag ids; Danger `RemovedTwigBlocks` for moved or removed Twig blocks.

Legitimate patterns:

- The removal mechanics themselves (feature-flag gate, `shopware.inactiveFeature` tag, `CompatTwigExtension` entry): they are required, not a finding.
- Removing `@internal` code, private code or tests: not a break.
- A removal whose PR or linked issue already states the measured impact and the decision against a compatibility path.
- New deprecations in a minor: owned by the BC guides and `release-docs`.
- Wording and placement of the UPGRADE entry: owned by the `release-docs` guide.

## Severity

Every REMOVAL-001 to REMOVAL-003 finding uses category `compatibility` and sets `requires_human: true`: whether a break ships is a proportional, human decision.

- `major`: a deprecated surface is removed in the major without a measured impact, or with significant impact and no compatibility path considered. Plugin authors must ship a new release before merchants can upgrade.
- `minor`: the impact is measured and low and an UPGRADE entry exists, but the PR does not say whether a compatibility path was considered.
- `minor`, category `maintainability`: code meant to disappear in the major is neither marked nor gated (REMOVAL-004).

A public surface removed without any deprecation cycle is `blocking` and belongs to the BC guides, not here.

## Retired

(none yet)
