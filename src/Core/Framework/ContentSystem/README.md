# ContentSystem

A data-driven layout system for serving structured content through the Store API. Layouts define element trees with data requirements and context distribution, rendered through an event-driven pipeline.

## Core Concepts

**Content Elements** - Building blocks of layouts. Each element has a component (e.g., `Sw:Product:Card`), properties for configuration, slots for child elements, and optional data requirements.

**Placeholders** - Dynamic values in properties using `{{key}}` syntax. For example, `{{productId}}` gets replaced with the actual product UUID from the URL.

**Slots** - Named containers within elements. Each slot can hold multiple child elements.

**Data Requirements** - Declarations of what data an element needs. The system loads this data automatically before rendering.

**Context** - Mechanism for parent elements to share data with descendants. Providers expose data, consumers receive it without explicit passing through intermediate elements.

## Content Sections

Three content sections with different resolution strategies:

**Main** (`/store-api/content/{path}`) — Entity-based rendering for Products, Categories, and Landing Pages. Layouts assigned per entity with sales channel fallback. See Adapter/.

**Header** (`/store-api/content-header*`) — Domain-aware singleton per domain/sales channel. Three-tier fallback: domain+channel → channel → global. See Adapter/.

**Footer** (`/store-api/content-footer*`) — Same domain-aware resolution as header.

Header and Footer are Storefront-owned sections: the Core ships none of their data wiring. The `ContentSection` enum that names them (`HEADER`, `FOOTER`, `MAIN`) lives in the Core, but their entity definitions, specification sources, and section resolvers are all registered by the Storefront module via `content-system.php`. This is intentional: headless deployments without the Storefront bundle operate without header/footer sections.

Each section supports four response formats: full, decomposed, skeleton, and data. See SalesChannel/ and Output/.

## Rendering Pipeline

The pipeline is source-independent — specification sources translate entity IDs into a `ResolvedContentLayout` (layout ID plus `RenderingSpecification`), and `ContentPipeline` renders without knowing the original data source.

1. **Specification Resolution** — Route calls `RenderingSpecificationResolver` (Adapter/) which iterates sources via `supports()` check, then assembles the `ResolvedContentLayout`. See Adapter/.
2. **Layout Loading** — `ContentRoute` retrieves the `ContentLayoutEntity` from the content-layout repository and wraps it in a `RenderableLayout` passed into the pipeline.
3. **Preparation** — `Layout/Scaffolding/StoredTreePreparer` brings the stored forest into shape: placeholder resolution (FULL mode only), then the virtual-root wrap, then the partial prune, and it records the outcome as a `Layout/Scaffolding/TreePreparationResult` (the pruned tree, the pre-prune forest, and the `RenderScaffolding`). `ContentPipeline` then rejects a repeated element id on the pre-prune forest, so a duplicate whose twin the prune discarded still fails the request. It hands both forests to `Rendering/WiringPlanner::plan()`, which validates the context wiring on that same pre-prune forest — a wiring defect inside a discarded subtree still fails the request — and derives the redistribute providers on the surviving tree. Everything up to and including the derivation runs on stored elements. `ContentTreePreparationEvent` is dispatched before all of them, over the stored tree, so a listener sees raw author content. See Event/Listener/.
4. **Rendering** — `Rendering/ElementLowering` turns the derived stored forest into the rendered forest: it resolves each element's data requirements across the whole forest, then resolves what context every element received, then mints the `RenderedElement` tree. FULL mode runs all three; SKELETON resolves no data, computes no deliveries, and mints structure only. See Rendering/.
5. **Finishing** — `ContentPipeline` finishes the rendered tree itself: virtual root cleanup, partial extraction, both driven by the `RenderScaffolding` recorded during preparation. `RenderedTreeFinalizationEvent` is then dispatched over that finished rendered tree, in both modes, so a listener sees and replaces the rendered model. Last, the pipeline rejects a repeated element id in the forest the event handed back — the second of its two duplicate-id checks, and the one that catches a listener's replacement, which the pre-lowering check ran too early to see. Both throw `DUPLICATE_ELEMENT_ID` (500, an internal fault rather than a client defect), and both run in either rendering mode. See Event/Listener/.

See [docs/data-flow.md](docs/data-flow.md) for a diagram of this pipeline's data flow.

## Key Classes

Module root:
- `ContentPipeline` - Orchestrates steps 3-5 of the rendering pipeline; receives the loaded `RenderableLayout` from the route
- `RenderableLayout` - Loaded layout handed to the pipeline: a `LayoutReference` plus its `list<StoredElement>`
- `LayoutReference` - Immutable layout identity: id, name, version
- `ResolvedContentLayout` - Resolver output: layout ID plus the `RenderingSpecification`
- `ContentSection` - Enum: HEADER, FOOTER, MAIN
- `RenderingSpecification` - Data requirements, placeholders, request, target element, cache tags
- `RenderingMode` - Enum: FULL (resolve data and context), SKELETON (structure only)
- `PlaceholderValues` - Immutable placeholder value map
- `SpecificationData` - Bundles data requirements (from the entity definition) with placeholder values (from the request path and query parameters), independent of layout assignment
- `DraftLayoutChecker` - Draft-layout check for the preview action (runs the `LayoutDiagnostics` intrinsic subset)

## Extension Model

Plugins extend the ContentSystem through six mechanisms, documented in [docs/extending.md](docs/extending.md).

## Domain Placement

Domain-specific content system classes live in their owning domain module — not centralized here. Both the class and its DI registration belong to the domain.

**Domain-owned:** Entity definitions, specification sources, data loaders, config serializers. These are co-located with the domain entity they serve (e.g., product data loader lives in the product module).

**Framework-owned (stays here):** Pipeline, render step (`Rendering/`) and data loaders (`Hydration/`), field serializers, cache, events, output formats, generic loaders, tagged locator consumers, route loader, type introspection schema.

**DI registration follows the class.** Tagged services (`content_system.data_loader`, `content_system.config_serializer`, `content_system.entity_specification_source`) are resolved via `tagged_locator`/`tagged_iterator` at compile time, regardless of which DI file defines them.

## Naming

The reasoning behind how classes in this module are named starts at [`NAMING.md`](NAMING.md), which routes on to the two subjects that need room of their own. Consult it before adding or renaming a type.

## Administration API

Admin-facing endpoints (layout preview, resolve-and-diagnose, the nine draft mutation actions, and the nine persisted mutation actions) are documented in [Api/README.md](Api/README.md), which also routes on to the four type-introspection endpoints the Administration consumes.

## Subdirectories

- **Adapter/** - [Adapter/README.md](Adapter/README.md) - Specification sources, layout assignment entities, resolution helpers
- **Api/** - [Api/README.md](Api/README.md) - Admin API controllers (layout preview, resolve-and-diagnose, the nine draft mutation actions, and the nine persisted mutation actions)
- **Binding/** - [Binding/README.md](Binding/README.md) - Binding specification system: declarations wiring a type's reference properties to loaders and seeding its primitive inputs — authored inline, or synthesized automatically from a `resolvedBy` reference property and fill-applied at scaffold/replace with no client action — plus explicit application via the `bind-element` mutation or an `insert-element` carrying a `bindingSpecificationId`
- **Cache/** - [Cache/README.md](Cache/README.md) - HTTP cache integration and invalidation
- **Diagnostics/** - [Diagnostics/README.md](Diagnostics/README.md) - Layout analysis: per-element property resolution plus a well-formedness/resolvability report
- **Event/** - [Event/README.md](Event/README.md) - Rendering lifecycle event definitions
- **Event/Listener/** - [Event/Listener/README.md](Event/Listener/README.md) - Listeners on the two rendering lifecycle events
- **Helper/** - Utility classes (ContentLayoutMetadataDeriver)
- **Hydration/** - [Hydration/README.md](Hydration/README.md) - The data-loading half of the render step: `DataLoader/` data fetching plus the remaining `DataContext/` utilities; the render step itself lives in Rendering/
- **Layout/** - [Layout/README.md](Layout/README.md) - Element tree, entities, field types, scaffolding, element type system, universal style options
- **Mutation/** - [Mutation/README.md](Mutation/README.md) - Server-side structural layout edits (insert, remove, move, replace, duplicate, wrap, unwrap, attach, bind), each re-resolved through the diagnostics pass; applied either statelessly to a draft tree or committed to a stored layout
- **Output/** - [Output/README.md](Output/README.md) - Response formatting and partial rendering
- **Rendering/** - [Rendering/README.md](Rendering/README.md) - The render step: data loading, context distribution, and the minting of the rendered tree
- **Resolution/** - [Resolution/README.md](Resolution/README.md) - Property-resolution kernel (element/context resolvers, resolution candidates)
- **SalesChannel/** - [SalesChannel/README.md](SalesChannel/README.md) - Store API endpoints
- **Schema/** - Data loader type introspection and schema generation
- **Validation/** - [Validation/README.md](Validation/README.md) - DAL write-time resolvability gate (`PreWriteValidationEvent` validators)
- [NAMING.md](NAMING.md) - How classes in this module are named
- [docs/product-detail-page.md](docs/product-detail-page.md) - A worked layout combining entity rendering, data loading, and context distribution
- [docs/service-tags-and-types.md](docs/service-tags-and-types.md) - The DI tags and the base classes, value objects, enums, and events an extension uses
- [docs/extending.md](docs/extending.md) - The six extension mechanisms and where each one is authored
- [docs/data-flow.md](docs/data-flow.md) - A diagram of the rendering pipeline's data flow
- **Storefront/ContentSystem/** - [Storefront/ContentSystem/README.md](../../../Storefront/ContentSystem/README.md) - Header and footer sections, which are Storefront-owned.

`Helper/` and `Schema/` carry no documentation surface of their own.
