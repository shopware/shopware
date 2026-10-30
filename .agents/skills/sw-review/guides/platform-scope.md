---
guide: platform-scope
title: Is this the platform's job?
personas: [maintainer, architecture]
rules:
  - { id: SCOPE-001, since: 2026-10-09, source: ".agents/skills/sw-review/guides/platform-scope.md", fixture: ["tests/guide/platform-scope/catch-cleanup-moved-into-command", "tests/guide/platform-scope/ignore"] }
  - { id: SCOPE-002, since: 2026-10-09, source: ".agents/skills/sw-review/guides/platform-scope.md", fixture: "tests/guide/platform-scope/catch-option-for-one-hosting" }
  - { id: SCOPE-003, since: 2026-10-09, source: ".agents/skills/shopware-change-scope/SKILL.md#bug-fixes", fixture: "" }
  - { id: SCOPE-004, since: 2026-10-09, source: ".agents/skills/sw-review/guides/platform-scope.md", fixture: "" }
  - { id: SCOPE-005, since: 2026-10-09, source: ".agents/skills/sw-review/guides/platform-scope.md", fixture: "" }
  - { id: SCOPE-006, since: 2026-10-09, source: ".agents/skills/sw-review/guides/platform-scope.md", fixture: "" }
  - { id: SCOPE-007, since: 2026-10-09, source: ".agents/skills/sw-review/guides/platform-scope.md", fixture: "" }
---

## Why this guide exists

Shopware runs in thousands of setups. A workaround for one setup runs in all of them, stays as product surface and hides the real problem from the operator. This guide is the source of these rules; there is no separate guideline. Whether the setup or the platform is wrong is a product decision, so every finding here is a question for a human.

## Check

- **SCOPE-001** Work moves out of a scheduled task or message handler into a command, request or compile step, or gains a fallback "in case the worker does not run". A running worker and task runner are requirements of Shopware, and the fallback also runs in every correctly configured shop. Ask to document the requirement instead. Example: theme cleanup moved into `theme:compile` because the task did not run somewhere; cached pages then loaded CSS and JS that returned 404.
- **SCOPE-002** A new config key, env var or command flag, or a changed default for every shop, whose only reason is one hosting variant. Options stay forever and double the test matrix. An option is fine when two supported setups need different behaviour. Example: private downloads were streamed through PHP for every shop because one setup's storage was not reachable from the browser; documenting the requirement was the fix.
- **SCOPE-003** A symptom fixed in a shared path (sync service, kernel, request listener) although one operation causes it. The central fix changes behaviour for every caller, including the correct ones. Ask to fix the causing layer. Rule: [shopware-change-scope](../../shopware-change-scope/SKILL.md#bug-fixes). Example: `SyncService` handled one operation's invalid reference for the whole request; that operation should fail and the others continue.
- **SCOPE-004** `getenv()`, `ini_get()`, `is_writable()`, `function_exists()` or `class_exists()` silently switching behaviour. Two shops on the same version then behave differently and no bug report explains why. Ask for a documented config key or env var, or a clear error at install or boot. Example: read-only filesystems got `SHOPWARE_SKIP_WEBINSTALLER` instead of a probe whether the project directory is writable.
- **SCOPE-005** Code for one product, plugin or service in Core, Storefront or Administration: SaaS or Commercial keys, direct calls into one integration, default UI changed for all shops because one extension misbehaves. Every shop carries that code, and core breaks when the product is missing. Ask for a generic extension point or event. Example: SaaS keys hard-coded in `ThemeCompiler`.
- **SCOPE-006** Core or the Administration depending on the Storefront bundle: Storefront imports, Storefront sources in the Administration build, logic that headless shops need living only in a Storefront subscriber. The Storefront is optional, so headless shops break or miss the behaviour. Example: SEO handling that only a Storefront subscriber ran.
- **SCOPE-007** An optional feature doing work while it is switched off: queries, HTTP calls or listeners on the cart, checkout, listing or request path before the "is active" check. Every shop that does not use the feature pays on its busiest paths. Example: an analytics feature must add no queries to cart, checkout or listings while it is off.

## Do not flag

CI covers:

- `NoEnvironmentHelperInsideCompilerPassRule` and `NoSuperGlobalsInsideCompilerPassRule` (PHPStan): environment reads inside compiler passes.

Legitimate patterns:

- Retries, backoff and timeouts inside a message handler, including `RecoverableMessageHandlingException`.
- Feature flags (`Feature::isActive()`) and major-flag removals: owned by the BC guides.
- A new option that names two supported setups, for example read-only and writable filesystems.
- Test code that stubs the environment.
- A generic extension point or event whose first user is one product (SCOPE-005); Storefront code that calls Core (SCOPE-006 is the other direction).
- A PR description that mentions "environments" while the code keeps the work async and adds no option. The description is evidence, never the trigger.

## Severity

Every SCOPE finding uses category `scope` and sets `requires_human: true`.

- `blocking`: the workaround can take a correctly configured shop offline, like the theme cleanup in `theme:compile` behind the HTTP cache. Pair it with the matching CACHE- finding.
- `major`: any other SCOPE-001 to SCOPE-007 finding that adds product surface, changes shared behaviour or costs every shop work.
- `minor`: an option without a named second setup that is not a workaround, or an explicit but undocumented environment switch.

## Retired

(none yet)
