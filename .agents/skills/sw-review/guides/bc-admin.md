---
guide: bc-admin
title: Administration components, blocks and overrides are plugin API
personas: [architecture, ux, maintainer]
rules:
  - { id: BCADM-001, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#administration", fixture: "tests/guide/bc-admin/catch" }
  - { id: BCADM-002, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#administration", fixture: "tests/guide/bc-admin/catch" }
  - { id: BCADM-003, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#administration", fixture: "" }
  - { id: BCADM-004, since: 2026-10-09, source: "adr/2026-08-10-administration-javascript-deprecation-guards.md#public-detectable-apis", fixture: "" }
  - { id: BCADM-005, since: 2026-10-09, source: "src/Administration/Resources/app/administration/technical-docs/03-extensibility/07-native-setup-authoring.md#setup-markers", fixture: "" }
  - { id: BCADM-006, since: 2026-10-09, source: "coding-guidelines/administration/feature-flags-and-deprecations.md#deprecations", fixture: "" }
  - { id: BCADM-007, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#administration", fixture: "" }
  - { id: BCADM-008, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#administration", fixture: "" }
  - { id: BCADM-009, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#administration", fixture: "" }
  - { id: BCADM-010, since: 2026-10-09, source: "adr/2026-08-10-administration-javascript-deprecation-guards.md#private-and-static-only-symbols", fixture: "" }
---

## Why this guide exists

Plugins and apps extend the Administration by using base components, overriding them, reaching parent members through `$super`, and overriding Twig blocks. A removed component, prop, slot or block therefore breaks extension screens on update, and no CI job checks Administration JS or Twig compatibility. The 6.8 breakage review found removing `sw-empty-state` alone would affect about 11% of customers. The full list of public surfaces is in [Administration](../../../../coding-guidelines/core/backward-compatibility.md#administration).

## Check

- **BCADM-001** Look for a component, prop, slot, event, method, module route, route parameter, position identifier or static asset that is removed or renamed without a deprecation. Plugins that use it lose content or fail to render the screen after the update. Deprecate first and keep the old name working until the major; an intended break needs a linked discussion or decision before the code. Rule: [Administration](../../../../coding-guidelines/core/backward-compatibility.md#administration). Example: the 6.8 breakage review found that removing `sw-empty-state` would hit about 11% of customers, and a deleted module icon under `static/img` broke every consumer that loaded it. A reworked mail template detail page and a removed block in the Storefront settings page broke public components without prior discussion.
- **BCADM-002** Look for a new prop with `required: true` on an existing component. Every plugin that already uses the component now gets a Vue warning or broken behaviour because it does not pass the prop. Add the prop as optional with a default, and warn when it is missing. Rule: [Administration](../../../../coding-guidelines/core/backward-compatibility.md#administration). Example: none recorded yet.
- **BCADM-003** Look for a Twig block in an Administration template that is removed, renamed or moved, including changes to `ref`, `v-if`, `v-model` or `v-bind` on elements inside overridden blocks. Unlike the Storefront, no CI check warns about this, and overrides of the block silently stop rendering. A changed entry in `blocks-list.json` or `position-identifiers.json` is the signal, also for blocks that look private and for typo fixes. Keep the block, or deprecate it and document the replacement block. Rule: [Administration](../../../../coding-guidelines/core/backward-compatibility.md#administration). Example: UPGRADE-6.8 "Removed Administration Twig blocks from legacy `sw-tabs` branches".
- **BCADM-004** Look for a renamed, removed or retyped `methods`, `computed` or `data` member on a component that plugins override; data moved to a computed property needs a setter. Overrides call it through `this.$super('name')`, which breaks at runtime. Treat these members as public unless they are `@private` or start with `_`. Rule: [ADR, public detectable APIs](../../../../adr/2026-08-10-administration-javascript-deprecation-guards.md#public-detectable-apis). Example: the order address selection component moved a `data` member into a computed property without a setter, so modules that inherit the component and assign the value broke.
- **BCADM-005** In a native setup base component (`<script setup>` with `swDefinePublic`), look for a key removed from `swDefinePublic({ ... })` or a renamed file. The key list is the override and template-ref surface, and the file name is the override target, so overrides and parents reading a ref break. Rule: [Setup markers](../../../../src/Administration/Resources/app/administration/technical-docs/03-extensibility/07-native-setup-authoring.md#setup-markers). Example: UPGRADE-6.8 "Composition API extension system is no longer a public entry point".
- **BCADM-006** Look at every new deprecation. It needs `@deprecated tag:v6.X.0 - <replacement>` and, for flags, the lowercase id (`v6.X.0.0`). Once the `Shopware.Feature.triggerDeprecationOrThrow()` helper is merged, public runtime-detectable APIs also need that guard, and the guard must not crash on a mistyped flag; until then, the docblock plus release notes are required. Without them, extension developers learn about the removal only when it breaks. Rule: [Deprecations](../../../../coding-guidelines/administration/feature-flags-and-deprecations.md#deprecations), [ADR runtime helper](../../../../adr/2026-08-10-administration-javascript-deprecation-guards.md#runtime-helper). Example: the first draft of the runtime deprecation helper threw on a mistyped flag id or a boolean, which would have crashed the mount of every component that called it.
- **BCADM-007** Look for a `sw-*` base component replaced by a Meteor `mt-*` component. The deprecation names the `mt-*` replacement, the old component keeps working and warns while the major flag is off, and the release notes describe the migration path; otherwise every plugin screen that uses it breaks with no guidance. Rule: [Administration](../../../../coding-guidelines/core/backward-compatibility.md#administration), [Deprecations](../../../../coding-guidelines/administration/feature-flags-and-deprecations.md#deprecations). Example: `sw-tabs` stays a working legacy component in 6.8 and warns developers who still use it, instead of being removed in favour of its Meteor replacement.
- **BCADM-008** Look for core switching to a new method, service or call signature while plugins still override the old one: core stops calling an overridden method, a factory ignores an argument that extensions pass, an extension-facing import fails with a type error on older versions. The override still loads, but its effect is silently gone. Keep calling the old member until the major (let it delegate), and give imports a readable failure. Rule: [Administration](../../../../coding-guidelines/core/backward-compatibility.md#administration). Example: the Axios compatibility layer in `http.factory.js` ignored the second argument, so configuration that extensions passed was lost. `sw-system-config` stopped calling `getConfig`, so plugins that override it silently lost their changes.
- **BCADM-009** Look for new logic written inline in a template expression of an Administration component. Plugins can change a method or computed property through `$super`, but cannot reach an expression inside a template without copying the whole block. Move the logic into a method or computed property. Rule: [Administration](../../../../coding-guidelines/core/backward-compatibility.md#administration). Example: new template logic in the CMS layout assignment modal had to move into a computed property so extensions can change it; the category SEO form moved a whole template expression into a method for the same reason.
- **BCADM-010** Look for a new component, service or method that is not marked `@private` although its API is not meant to be public yet. Without the marker it is public API from its first release, and every later change needs a deprecation. Rule: [Administration](../../../../coding-guidelines/core/backward-compatibility.md#administration), [ADR, private symbols](../../../../adr/2026-08-10-administration-javascript-deprecation-guards.md#private-and-static-only-symbols). Example: a new `documentV2` API service and a new company information settings component shipped without `@private`, although their API was not stable yet.

## Do not flag

### CI covers

- ESLint (`composer eslint:admin`), including `require-position-identifier` for Meteor SDK position identifiers.
- Danger `RemovedTwigBlocks` covers Storefront templates only; it is not a guard for Administration blocks (see BCADM-003).

### Legitimate patterns

- Members marked `@private` on their own declaration and identifiers prefixed with `_`: no BC duty ([ADR](../../../../adr/2026-08-10-administration-javascript-deprecation-guards.md#private-and-static-only-symbols)).
- The Composition API override hooks (`createExtendableSetup`, `overrideComponentSetup`, `attachOverrides`, `registerOverrideComponent`, `getOverrideComponents`): they are `@private`.
- New optional props with defaults, new slots, blocks, events and methods.
- Test code and Jest specs.
- Changes behind the next major flag on trunk (`v6.X.0.0`) whose flag-off path behaves like the previous release and which carry a deprecation. Flag ids, flag nesting, the deletable legacy branch, flag tests and removed `blocks-list.json` or `position-identifiers.json` entries belong to the admin-bc-and-flags guide.
- Components and modules marked `@experimental` (their shape may change without deprecation; removing the whole feature in a minor is still a finding, see [Flags and experimental](../../../../coding-guidelines/core/backward-compatibility.md#flags-and-experimental)).

## Severity

- `blocking`: a public component, prop, slot, block, event, method or route is removed or renamed in a minor without deprecation, or a required prop is added, so existing plugin screens break on update.
- `major`: a deprecation exists but lacks a replacement, a migration path or the runtime guard once the helper exists. Set `requires_human: true` for every intended public API break: the [Administration rules](../../../../src/Administration/Resources/app/administration/AGENTS.md#coding-guidelines) require prior discussion, and a component-level `@private` on a widely used `sw-*` component needs a human to weigh usage.
- `major`: core bypasses a plugin override (BCADM-008), so plugin screens lose their changes without an error.
- `minor`: the deprecation tag format or flag id spelling is wrong, template logic that overrides cannot reach (BCADM-009), or a new component or service without `@private` (BCADM-010).

## Retired

(none yet)
