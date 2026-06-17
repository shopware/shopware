# Administration Internal Architecture Docs (Draft Skeleton)

> Purpose: Internal-facing technical documentation for the Shopware 6 Administration (Vue application). Each linked document currently contains only a bullet list of intended content. Expand iteratively.

## Sections

- Overview (`01-overview/`)
- Architecture (`02-architecture/`)
- Extensibility (`03-extensibility/`)
- Data Layer (`04-data-layer/`)
- Global object (`05-global-object/`)
- UI / Component Library (`06-ui/`)
- Testing (`07-testing/`)
- Internationalization & Snippets (`08-i18n/`)
- Security (`09-security/`)
- Commercial (`10-commercial/`)

## Coding Guidelines

Normative Administration rules live in:

- [Administration architecture](../../../../../../coding-guidelines/administration/architecture.md)
- [Administration testing](../../../../../../coding-guidelines/administration/testing.md)
- [Administration feature flags and deprecations](../../../../../../coding-guidelines/administration/feature-flags-and-deprecations.md)

## Related ADRs

- [Co-locate Administration Technical Documentation with Source Code](../../../../../../adr/2025-10-14-colocate-administration-technical-docs.md)

## Expansion Guidelines

- Keep audience: core & solution team engineers + contributors
- Start each file with: Status badge (Draft / In-Progress / Stable) once content exists
- Prefer Mermaid for flows (boot, extension lifecycle, message channels)
- Cross-link existing ADRs where relevant
- Mark experimental features clearly (e.g. Native Block System, Composition API extensions)

## Contribution Workflow (to be detailed later)

- Propose structure changes first via PR label `docs-architecture`
- Keep PRs small & topic-focused
- Add references to ADR numbers where applicable

## Quick Map (First Wave)

- Overview → What & why, headless nature, high-level data + extension flow diagram
- Architecture → Folder structure, boot, module system, state management
- Extensibility → Plugins vs Apps, current vs future component extension systems
- Data Layer → Repository pattern, Criteria, API services, caching hooks
- Runtime → Global Shopware object, feature flags, context/auth lifecycles
- UI → Meteor vs legacy `sw-` components, design tokens, accessibility approach
- Testing → Unit, integration, E2E strategy, fixture datasets
- i18n → Snippet loading & generation, fallback rules
- Security → ACL, iframe hardening, CSP, permission resolution
- Performance → Build splitting, lazy loading, hydration considerations (future), profiling
- Commercial → Purpose & integration points
- (Removed internal-only planning sections: roadmap, meta, observability)

---
(Draft skeleton – do not publish externally yet)
