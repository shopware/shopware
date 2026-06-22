# ContentSystem

A data-driven layout system for serving structured content through the Store API. Layouts define element trees with data requirements and context distribution, rendered through an event-driven pipeline.

## Content Sections

Three content sections with different resolution strategies:

**Main** (`/store-api/content/{path}`) — Entity-based rendering for Products, Categories, and Landing Pages. Layouts assigned per entity with sales channel fallback. See Adapter/.

**Header** (`/store-api/content-header*`) — Domain-aware singleton per domain/sales channel. Three-tier fallback: domain+channel → channel → global. See Adapter/.

**Footer** (`/store-api/content-footer*`) — Same domain-aware resolution as header.

Header and Footer are Storefront-owned sections: the Core ships none of their data wiring. The `ContentSection` enum that names them (`HEADER`, `FOOTER`, `MAIN`) lives in the Core, but their entity definitions, specification sources, and section resolvers are all registered by the Storefront module via `content-system.xml`. This is intentional: headless deployments without the Storefront bundle operate without header/footer sections.

Each section supports four response formats: full, decomposed, skeleton, and data. See SalesChannel/ and Output/.

## Rendering Pipeline

The pipeline is source-independent — specification sources translate entity IDs into a `ResolvedContentLayout` (layout ID plus `RenderingSpecification`), and `ContentPipeline` renders without knowing the original data source.

1. **Specification Resolution** — Route calls `RenderingSpecificationResolver` (Adapter/) which iterates sources via `supports()` check, then assembles the `ResolvedContentLayout`. See Adapter/.
2. **Layout Loading** — `ContentRoute` retrieves the `ContentLayoutEntity` from the content-layout repository and wraps it in a `RenderableLayout` passed into the pipeline.
3. **PreHydration Events** — Listeners prepare layout: virtual root wrapping, redistribute-flag expansion, placeholder resolution, partial rendering pruning. See Event/Listener/.
4. **Hydration** (FULL mode only) — `ContentElementHydrator` loads data per element's requirements, then distributes context. Skipped in SKELETON mode. See Hydration/.
5. **PostHydration Events** — Listeners finalize: virtual root cleanup, partial extraction. See Event/Listener/.

## Key Classes

Module root:
- `ContentPipeline` - Orchestrates steps 3-5 of the rendering pipeline; receives the loaded `RenderableLayout` from the route
- `RenderableLayout` - Loaded layout handed to the pipeline: a `LayoutReference` plus its `list<ContentElement>`
- `LayoutReference` - Immutable layout identity: id, name, version
- `ResolvedContentLayout` - Resolver output: layout ID plus the `RenderingSpecification`
- `ContentSection` - Enum: HEADER, FOOTER, MAIN
- `RenderingSpecification` - Data requirements, placeholders, request, target element, cache tags
- `RenderingMode` - Enum: FULL (hydrate), SKELETON (structure only)
- `PlaceholderValues` - Immutable placeholder value map
- `SpecificationData` - Bundles data requirements (from the entity definition) with placeholder values (from the request path and query parameters), independent of layout assignment
- `DraftLayoutChecker` - Draft-layout check for the preview action (runs the `LayoutDiagnostics` intrinsic subset)

## Domain Placement

Domain-specific content system classes live in their owning domain module — not centralized here. Both the class and its DI registration belong to the domain.

**Domain-owned:** Entity definitions, specification sources, data loaders, config serializers. These are co-located with the domain entity they serve (e.g., product data loader lives in the product module).

**Framework-owned (stays here):** Pipeline, hydration engine, field serializers, cache, events, output formats, generic loaders, tagged locator consumers, route loader, type introspection schema.

**DI registration follows the class.** Tagged services (`content_system.data_loader`, `content_system.config_serializer`, `content_system.entity_specification_source`) are resolved via `tagged_locator`/`tagged_iterator` at compile time, regardless of which XML file defines them.

## Naming

The reasoning behind how classes in this module are named (the subjects, role-suffix contracts, and domain vocabulary a new class should follow) lives in [`NAMING.md`](NAMING.md). Consult it before adding or renaming a type.

## Administration API

Admin-facing endpoints (layout preview, resolve-and-diagnose, the seven draft mutation actions, the seven persisted mutation actions, plus the type-introspection routes the Administration consumes) are documented in `ADMINISTRATION.md`.

## Subdirectories

- **Adapter/** - Specification sources, layout assignment entities, resolution helpers
- **Api/** - Admin API controllers (layout preview, resolve-and-diagnose, the seven draft mutation actions, and the seven persisted mutation actions)
- **Binding/** - Source-binding enumeration for the resolvability gate (`LayoutBindingEnumerator` extension point)
- **Cache/** - HTTP cache integration and invalidation
- **Diagnostics/** - Layout analysis: per-element property resolution plus a well-formedness/resolvability report
- **Event/** - Hydration lifecycle event definitions
- **Event/Listener/** - Pre/post hydration pipeline transformations
- **Helper/** - Utility classes (ContentLayoutMetadataDeriver)
- **Hydration/** - Data loading and context distribution
- **Layout/** - Element tree, entities, field types, scaffolding, element type system
- **Mutation/** - Server-side structural layout edits (insert, remove, move, replace, duplicate, wrap, unwrap, attach), each re-resolved through the diagnostics pass; applied either statelessly to a draft tree or committed to a stored layout
- **Output/** - Response formatting and partial rendering
- **Resolution/** - Property-resolution kernel (element/context resolvers, resolution candidates)
- **SalesChannel/** - Store API endpoints
- **Schema/** - Data loader type introspection and schema generation
- **Validation/** - DAL write-time resolvability gate (`PreWriteValidationEvent` validators)
