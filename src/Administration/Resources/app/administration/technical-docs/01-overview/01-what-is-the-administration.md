---
title: "What Is The Administration?"
status: Draft
description: High-level overview of the Shopware 6 Administration application – purpose, positioning, architecture, capabilities and extensibility surface.
---

# What Is The Administration?

> The Administration is the merchant & operator facing single-page application of Shopware 6: the place where business users manage catalog, content, orders, customers, configuration and commercial extensions. It is not the storefront (buyer experience) and not the raw headless API platform, it is an application built *on top* of those platform APIs.

## Positioning & Purpose

The Administration provides a cohesive UI layer over Shopware's domain APIs. Core goals:

1. Unified management of commerce features (products, orders, customers, media, rules, promotions, content, ...)
2. Configuration (system settings, sales channel / locale / currency / tax setup)
3. Operational workflows (fulfilment, content scheduling, bulk edits, data import/export)
4. Extensibility with Plugins (directly script injected) & Apps (isolated and remote based on iframes)
5. Safe multi-user access via permissions (ACL / role-based) and context separation (sales channel & language)
6. Productivity & consistency through shared component library (Meteor components + custom `sw-` components)

## Technology Stack (High-Level)

| Concern | Technology / Concept |
|---------|----------------------|
| Framework | Vue.js 3 |
| Language | Javascript and TypeScript (gradual) |
| Build | Vite multi build and watch for automatic injection of plugins |
| Styling | SCSS + design tokens + CSS variables |
| Data Layer | Repository Pattern + Criteria abstraction over REST API |
| State | Local and global reactive state with Pinia |
| Extensibility | Injection of custom code with plugins / Apps with Meteor Admin SDK |
| Auth | API bearer token |
| Internationalization | Snippet system (namespaced JSON) with fallback chain |

Location in repository: `src/Administration/Resources/app/administration` (source) → built admin accessible with `/admin` route served by Core

## Mental Model / System Context

The Administration is the headless merchant & operator facing single-page application (SPA): it does not render server-side HTML. It bootstraps once, then communicates via REST (and selected async channels) to the backend. Extensions can inject code at boot or run remotely.

## Core Responsibilities

1. Entity Management (create, read, update, delete) with validation & versioning aids
2. Cross-domain workflows (Bulk Edit, Import/Export, Rule Builder integration)
3. Configuration surfaces (system config, sales channel config, plugin/app config)
4. Shopping experiences, content management (CMS pages, layouts, categories, navigation)
5. Operational tooling (Order fulfilment statuses, Customer service, Media handling)
6. Observability & feedback (notifications, activity indicators, error & deprecation surfacing)

## Architectural Characteristics

| Aspect | Summary                                                                                                                                                                                                                                |
|--------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Boot Process | Progressive initialization: env/meta load → feature flag evaluation → service/container setup → module registration → login / context establishment → root component mount. See [Boot Process](../02-architecture/01-boot-process.md). |
| Module System | Business domains encapsulated as modules (routes, components, stores, privileges, snippets). See [Module System](../02-architecture/03-module-system.md).                                                                              |
| Data Access | Repositories produce entity collections / entities via Criteria (filters, pagination, associations). See [Data Layer Overview](../04-data-layer/01-overview.md).                                                                       |
| Runtime Globals | Central `Shopware` object exposes factories, service container, feature flag API, component registry. See [Global Shopware Object](../05-runtime/01-global-shopware-object.md).                                                        |
| Feature Flags & Deprecations | Gate experimental / versioned behavior and surface upgrade warnings. See [Feature Flags & Deprecations](../05-runtime/02-feature-flags-deprecations.md).                                                                               |
| UI Library | Migration from `sw-` components to meteor library components, plus design tokens. See [Meteor Component Library](../06-ui/01-meteor-component-library.md).                                                                             |
| Testing | Jest unit tests + integration specs And Playwright E2E tests. See [Testing Overview](../07-testing/01-overview.md).                                                                                                                    |

## Data Layer Snapshot

The Administration does not directly couple UI components to REST endpoints. Instead, a Repository abstraction (+ generated entity schemas) offers a consistent interface: `repository.search(criteria)`, `repository.save(entity)`, with Criteria describing filters, sorts, pagination & association loads. This allows:
* Local entity draft manipulation before persistence
* Smaller payloads with automatic changset generation on save
* Extension of entities (custom fields, associations) without core code changes
* Central handling of versioning & translated fields

Caching / batching considerations and advanced hooks are described in the Data Layer section.

## Extensibility Surfaces

Two major categories:

1. Plugins (server-installed): can inject / override admin resources (components, routes, services, snippets, locales, webpack entries). Strong coupling to platform version.
2. Apps (remote): integrated via iframes + Meteor Admin SDK (postMessage bridge). Decoupled deployment, permission-scoped capabilities.

Additional extension mechanisms:
* Component override / `extend` pattern together with Twig block system inside Vue components (current)
* Future Native Block System (see [Future Native Blocks](../03-extensibility/05-future-native-blocks.md)) with [extendable Composition API](https://developer.shopware.com/docs/guides/plugins/plugins/administration/module-component-management/customizing-components.html#experimental-composition-api-extension-system)
* Custom Fields reflected in forms automatically via schema resolution

See [Extensibility Overview](../03-extensibility/01-overview.md), [Plugins](../03-extensibility/02-plugins.md), and [Apps](../03-extensibility/03-apps.md).

## Security & Permissions

* Per-action ACL privileges aggregated into roles → user assignments
* Frontend checks (route guards, component conditionals) complement backend enforcement
* App iframes isolated; capabilities mediated by App Bridge permission model
* Sanitization & CSP considerations for injected HTML (expanded in [ACL & Permissions](../09-security/01-acl-permissions.md) and [App Iframe Security](../09-security/02-app-iframe-security.md))

## Performance Notes

* Code splitting by components with Vite dynamic imports
* Repository queries can be tuned via Criteria association selects to reduce over-fetching

More in [Performance Overview](../10-performance/01-overview.md) and [Code Splitting](../10-performance/02-code-splitting.md).

## Upgrade & Deprecation Strategy

* Feature flags guard new behavior until stable
* Deprecations emit console notices (Jest fail-on-console in place to force cleanup) referencing version removal targets
* Extensions should avoid relying on undocumented internal module internals

## Glossary (Initial)

| Term | Definition |
|------|------------|
| Repository | Front-end abstraction for CRUD + search on an entity definition |
| Criteria | Declarative object describing filters/sorts/pagination/associations |
| Module | Packaged domain feature: routes + components + stores + privileges |
| Feature Flag | Toggle gating new or deprecated logic paths |
| App Bridge | postMessage channel with permission-scoped APIs for remote apps |
| Meteor Component Library | Modern component library replacing current `sw-*` components |
