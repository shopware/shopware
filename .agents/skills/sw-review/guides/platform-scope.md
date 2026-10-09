---
guide: platform-scope
title: Is this the platform's job?
personas: [maintainer, architecture]
rules:
  - { id: SCOPE-001, since: 2026-10-09, source: "coding-guidelines/core/platform-scope.md#environment", origin: "https://github.com/shopware/shopware/pull/18612", fixture: ["tests/guide/platform-scope/catch-18612", "tests/guide/platform-scope/ignore"] }
  - { id: SCOPE-002, since: 2026-10-09, source: "coding-guidelines/core/platform-scope.md#options", origin: "https://github.com/shopware/shopware/pull/20493", fixture: "tests/guide/platform-scope/catch-20493" }
  - { id: SCOPE-003, since: 2026-10-09, source: "coding-guidelines/core/platform-scope.md#root-cause", origin: "https://github.com/shopware/shopware/pull/21173", fixture: "" }
  - { id: SCOPE-004, since: 2026-10-09, source: "coding-guidelines/core/platform-scope.md#explicit-environment", origin: "https://github.com/shopware/shopware/pull/12497", fixture: "" }
  - { id: SCOPE-005, since: 2026-10-09, source: "coding-guidelines/core/platform-scope.md#product-specific", origin: "https://github.com/shopware/shopware/pull/16486", fixture: "" }
  - { id: SCOPE-006, since: 2026-10-09, source: "coding-guidelines/core/platform-scope.md#bundle-boundaries", origin: "https://github.com/shopware/shopware/pull/16432", fixture: "" }
  - { id: SCOPE-007, since: 2026-10-09, source: "coding-guidelines/core/platform-scope.md#optional-features", origin: "https://github.com/shopware/shopware/pull/18599", fixture: "" }
---

## Why this guide exists

Shopware is standard software. Every workaround for a shop that is not set up as required makes the product more complex for everyone and hides the real problem from the operator. #18612 moved theme cleanup out of the scheduled task because the task did not run in some environments; it took the extension store storefront offline (#21010). #20493 streamed private downloads through PHP for every shop because one hosting setup had a storage endpoint the browser could not reach; it was closed in favour of documenting the requirement.

## Check

- **SCOPE-001** Look for work that moves out of a scheduled task or message handler into a command or request, or a new fallback "in case the worker does not run". The fallback also runs in every correctly configured shop, so all shops pay for a few misconfigured ones. Rule: [platform-scope.md#environment](../../../../coding-guidelines/core/platform-scope.md#environment). Example: #18612, `ThemeCompileCommand.php` calling `UnusedThemeDirectoryDeleter`.
- **SCOPE-002** Look for a new config key, env var or command flag, or a changed default for every shop, whose only reason is one infrastructure variant. Each option doubles the test matrix and stays forever, while a documented requirement costs nothing. Rule: [platform-scope.md#options](../../../../coding-guidelines/core/platform-scope.md#options). Example: #20493, `DownloadService.php` and `DownloadResponseGenerator.php` dropping presigned redirects for all shops.
- **SCOPE-003** Look for a symptom handled at a central place (sync service, kernel, request listener) although one operation caused it. A central fix changes behaviour for every caller, including the correct ones. Rule: [platform-scope.md#root-cause](../../../../coding-guidelines/core/platform-scope.md#root-cause). Example: #21173, `SyncService.php`; the failing operation should be marked failed and the others continue.
- **SCOPE-004** Look for `getenv()`, `ini_get()`, `is_writable()`, `function_exists()` or `class_exists()` that silently switch runtime behaviour. Two shops on the same version then behave differently and no bug report explains why. Rule: [platform-scope.md#explicit-environment](../../../../coding-guidelines/core/platform-scope.md#explicit-environment). Example: #12497, the documented `SHOPWARE_SKIP_WEBINSTALLER` variable instead of probing for a writable directory.
- **SCOPE-005** Look for code that exists for one product, plugin or service: SaaS or Commercial keys hard-coded in core, the Storefront calling an analytics integration directly, core UI changed for every shop because one extension shows a duplicate, feature-specific fields added to a core entity. Every shop carries and tests that code, and core breaks or misbehaves when the product is missing. Offer a generic extension point or event and keep the logic in the product. Rule: [platform-scope.md#product-specific](../../../../coding-guidelines/core/platform-scope.md#product-specific). Example: #16486 (SaaS keys in `ThemeCompiler.php`), #20682 (a Storefront plugin failing without Commercial), #20822 (plugin-specific rule data in core).
- **SCOPE-006** Look for Core or the Administration depending on the Storefront bundle: imports or build config that pull in Storefront sources, Storefront-only checks in Core, or logic in a Storefront subscriber that headless shops also need. The Storefront is optional, so headless shops break or silently miss the behaviour. Rule: [platform-scope.md#bundle-boundaries](../../../../coding-guidelines/core/platform-scope.md#bundle-boundaries). Example: #16432 (Administration build merging Storefront sources), #19690 (SEO handling only in a Storefront subscriber), #18150 (Storefront check in Core's `AreaResolver.php`).
- **SCOPE-007** Look for an optional feature that does work while it is switched off: queries, HTTP calls or listeners on the cart, checkout, listing or request path that run before checking that the feature is active. Every shop that does not use the feature pays for it on its busiest paths. Rule: [platform-scope.md#optional-features](../../../../coding-guidelines/core/platform-scope.md#optional-features). Example: #18599 (analytics must add no queries to cart, checkout or listings).

## Do not flag

CI covers:

- `NoEnvironmentHelperInsideCompilerPassRule` and `NoSuperGlobalsInsideCompilerPassRule` (PHPStan) already reject environment reads inside compiler passes.

Legitimate patterns:

- Retries, backoff and timeouts inside a message handler, including `RecoverableMessageHandlingException`: that is correct async behaviour.
- Explicit, documented feature flags (`Feature::isActive()`) and the major-flag removal tags: owned by the BC guides.
- A new option that names two supported setups needing different behaviour (for example read-only filesystems and writable ones).
- Test code that stubs the environment.
- Generic extension points and events that every plugin can use, even when one product is the first user (SCOPE-005); Storefront code that calls Core (SCOPE-006 is about the other direction).
- A PR description that mentions "environments" while the code keeps the work async and adds no option. The description is evidence for a finding, never the trigger.

## Severity

Every SCOPE finding uses category `scope` and sets `requires_human: true`: whether the setup or the platform is wrong is a product decision.

- `blocking`: the workaround can take a correctly configured shop offline, as #18612 did through the HTTP cache. Pair it with the matching CACHE- finding.
- `major`: a workaround for an environment problem that adds product surface (sync cleanup, fallback path, option or changed default for one setup), a symptom fix in a shared path, product-specific logic in core (SCOPE-005), a dependency on the Storefront bundle (SCOPE-006), or work done by a switched-off feature on a hot path (SCOPE-007).
- `minor`: an option without a named second setup that is not a workaround, or an environment branch that is explicit but undocumented.

## Retired

(none yet)
