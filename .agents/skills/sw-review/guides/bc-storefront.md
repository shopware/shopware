---
guide: bc-storefront
title: Storefront templates, JS plugins and styles are theme and plugin API
personas: [architecture, ux, maintainer]
rules:
  - { id: BCSF-001, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#storefront-twig-js-scss", fixture: "tests/guide/bc-storefront/catch" }
  - { id: BCSF-002, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#storefront-twig-js-scss", fixture: "" }
  - { id: BCSF-003, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#storefront-twig-js-scss", fixture: "" }
  - { id: BCSF-004, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#storefront-twig-js-scss", fixture: "" }
  - { id: BCSF-005, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#storefront-twig-js-scss", fixture: "tests/guide/bc-storefront/catch" }
  - { id: BCSF-006, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#deprecation-and-announcement", fixture: "" }
  - { id: BCSF-007, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#storefront-twig-js-scss", fixture: "" }
  - { id: BCSF-008, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#storefront-twig-js-scss", fixture: "" }
  - { id: BCSF-009, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#what-counts-as-a-break", fixture: "" }
---

## Why this guide exists

Themes and plugins extend the Storefront by overriding Twig blocks, including and extending templates, reading Twig variables, overriding JS plugins and their options, and compiling against SCSS variables. No PHP signature check sees any of this, so a rename that looks like cleanup breaks shops on update. The 6.8 breakage review found several such changes, each affecting 6-12% of plugins. Every item below is public API per [Public API is every extension surface](../../../../coding-guidelines/core/backward-compatibility.md#public-api-is-every-extension-surface).

## Check

- **BCSF-001** Look for a Twig block that is removed, renamed or moved to another parent, and for an opening HTML tag that moves out of or into a block. A theme that overrides the block loses its markup, or renders a second tag whose attributes lose against the first opening tag, so its classes and data attributes silently stop working. Deprecate with `{% deprecated %}`; for a rename, keep the old block inside the new one. Rule: [Storefront](../../../../coding-guidelines/core/backward-compatibility.md#storefront-twig-js-scss). Example: a block in `address-personal.html.twig` was renamed as cleanup; reviewers asked to keep the old name because theme overrides of it would break.
- **BCSF-002** Look for a Twig variable or `sw_include` parameter that is renamed, removed or changes type, and for existing content wrapped in a new block that changes variable scope. Theme overrides read these variables and then render empty output without any error. Keep the old variable next to the new one until the major. Rule: [Storefront](../../../../coding-guidelines/core/backward-compatibility.md#storefront-twig-js-scss). Example: the `hasChildren` variable and controller template variables used by about 9% of plugins were queued for removal; a renamed `sw_include` parameter in the address manager modal had to pass both names.
- **BCSF-003** Look for a removed or renamed Twig function or filter. Plugin templates that call it no longer compile, so every page using them fails. Deprecate it and keep it forwarding to the replacement until the major. Rule: [Storefront](../../../../coding-guidelines/core/backward-compatibility.md#storefront-twig-js-scss). Example: the `category_url` and `category_linknewtab` Twig functions were queued for removal while about 6% of plugins still call them.
- **BCSF-004** Look for a deleted or moved template file. `sw_include` paths and `sw_extends` targets are public, so extensions that include or extend the old path fail to render. Keep a forwarding template at the old path. Rule: [Storefront](../../../../coding-guidelines/core/backward-compatibility.md#storefront-twig-js-scss). Example: `cart-alerts.html.twig` was kept because about 12% of plugins include or extend it.
- **BCSF-005** Look for a JS plugin whose registered name, public method, emitted event or `static options` entry is removed or renamed. Options are public: themes set them through `data-*-options` attributes and plugin overrides read `this.options`, so the setting is ignored without a warning. Add new options with defaults and deprecate old ones. Rule: [Storefront](../../../../coding-guidelines/core/backward-compatibility.md#storefront-twig-js-scss). Example: the `pathIdList` option of the navbar plugin was queued for removal in the major; the breakage review counted it as plugin-facing API that needs a compatibility path first.
- **BCSF-006** Look for a deprecated JS plugin property or option that warns only in the constructor or `init()`. An override that reads it later gets no warning before the major removes it. Put the warning on the read access, for example a getter. Rule: [Deprecation and announcement](../../../../coding-guidelines/core/backward-compatibility.md#deprecation-and-announcement). Example: the HTTP client service warned in its constructor, so the warning fired on core's own use instead of when a plugin read the deprecated member. A deprecated option of the CMS form plugin raised an error only once the 6.8 flag was active, with no warning before.
- **BCSF-007** Look for a JS helper or service that is deleted instead of phased out. Plugins import helpers by path, so a deletion breaks the theme build of every plugin that uses them. Keep a thin wrapper around the replacement with a soft deprecation until the major. Rule: [Storefront](../../../../coding-guidelines/core/backward-compatibility.md#storefront-twig-js-scss). Example: deleting the `DomAccess` helper in the major would break the theme build of every plugin that imports it.
- **BCSF-008** Look for a removed or renamed SCSS variable, mixin, CSS selector (including `is--*`), `theme.json` config field or snippet key. Themes compile against these, saved theme configuration refers to field names, and plugin templates translate snippet keys. Keep the old names and map them to the new ones. Rule: [Storefront](../../../../coding-guidelines/core/backward-compatibility.md#storefront-twig-js-scss). Example: the `theme.json` translations were kept as fields and mapped to the new format.
- **BCSF-009** Look for a change in what the Storefront does at runtime that plugins, apps or merchants rely on, made without the major flag: a logout that clears all cookies and local storage, a template condition that now prints different data. No signature changes, so nothing warns, but plugin state disappears or merchants see different output after a routine update. Keep the old behaviour as the default and put the new one behind the major flag. Rule: [What counts as a break](../../../../coding-guidelines/core/backward-compatibility.md#what-counts-as-a-break), [Flags and experimental](../../../../coding-guidelines/core/backward-compatibility.md#flags-and-experimental). Example: logout cleared all cookies and local storage, which wiped the client-side state of plugins; it became an opt-in `Clear-Site-Data` header. The condition of the document position header switched from the billing to the shipping address.

## Do not flag

### CI covers

- Danger `RemovedTwigBlocks` warns when a block name disappears from a template under `src/Storefront/Resources/views`. It is a warning only and cannot stop a merge, and it does not see moved tags, variable scope or other paths, so BCSF-001 still applies.
- ESLint (`composer eslint:storefront`) and ludtwig cover JS and Twig syntax and style, not API compatibility.

### Legitimate patterns

- JS members prefixed with `_` and members marked `@private`: they are private and need no deprecation.
- Markup changes inside a block that keep the block, its parent and its variables.
- Test code, Jest specs and fixtures.
- Changes behind the next major flag on trunk (`v6.X.0.0`) whose flag-off path behaves like the previous release and which carry a deprecation.
- Templates, blocks and plugins marked `@experimental` (their shape may change without deprecation; removing the whole feature in a minor is still a finding, see [Flags and experimental](../../../../coding-guidelines/core/backward-compatibility.md#flags-and-experimental)).

## Severity

- `blocking`: a public Storefront surface is removed or renamed in a minor without deprecation, so a theme or plugin breaks on update (missing markup, template error, ignored option, failed theme build).
- `major`: a deprecation exists but the compatibility path is incomplete: no forwarding template or wrapper, or the warning fires only at construction. Set `requires_human: true` when the PR argues the surface is unused and no usage data backs it.
- `minor`: the deprecation note lacks the version tag or the replacement.

## Retired

(none yet)
