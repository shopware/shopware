# ContentSystem

A data-driven layout system for serving structured content through the Store API. Layouts define element trees with data requirements and context distribution, rendered through an event-driven pipeline.

## Design Principles

The module is built on a set of written design rules, one file per area under [docs/principles/](docs/principles/README.md). Much of the code is only understandable against those rules: a redundant-looking check, a value never repaired, a null kept apart from an absent key.

## Core Concepts

**Content Elements** - Building blocks of layouts. Each element has a component (e.g., `Sw:Product:Card`), properties for configuration, slots for child elements, and optional data requirements.

**Placeholders** - Dynamic values in properties using `{{key}}` syntax. For example, `{{productId}}` gets replaced with the actual product UUID from the URL.

**Slots** - Named containers within elements. Each slot can hold multiple child elements.

**Data Requirements** - Declarations of what data an element needs. The system loads this data automatically before rendering.

**Context** - Mechanism for elements to share data: [providers](docs/principles/README.md#glossary) expose data and [consumers](docs/principles/README.md#glossary) receive it, within the reach rules of [context-wiring](docs/principles/context-wiring.md#context-flows-only-between-adjacent-elements).

## Content Sections

Three content sections with different resolution strategies:

**Main** (`/store-api/content/{path}`) — Entity-based rendering for Products, Categories, and Landing Pages. Layouts assigned per entity with sales channel fallback. See Adapter/.

**Header** (`/store-api/content-header*`) — Domain-aware singleton per domain/sales channel. Three-tier fallback: domain+channel → channel → global. See Adapter/.

**Footer** (`/store-api/content-footer*`) — Same domain-aware resolution as header.

Header and Footer are Storefront-owned sections: the Core ships none of their data wiring. The `ContentSection` enum that names them (`HEADER`, `FOOTER`, `MAIN`) lives in the Core. Their entity definitions, specification sources, and section resolvers are all registered by the Storefront module via `content-system.php`. This is intentional: headless deployments without the Storefront bundle operate without header/footer sections.

Each section supports these response formats: full, decomposed, skeleton, and data. See SalesChannel/ and Output/.

## Rendering Pipeline

The pipeline is source-independent — specification sources translate entity IDs into a `ResolvedContentLayout` (layout ID plus `RenderingSpecification`), and `ContentPipeline` renders without knowing the original data source.

1. **Specification Resolution** — the route picks a source through `RenderingSpecificationResolver` and assembles the `ResolvedContentLayout`. See Adapter/.
2. **Layout Loading** — the route loads the `ContentLayoutEntity` and wraps it in a `RenderableLayout` for the pipeline.
3. **Preparation** — `Layout/Scaffolding/StoredTreePreparer` makes the stored [forest](docs/principles/README.md#glossary) renderable, then `Rendering/WiringPlanner::plan()` validates the context wiring and derives providers for `redistribute: true` consumers. Everything here runs on stored elements, and `ContentTreePreparationEvent` is dispatched ahead of all of it. See Rendering/ and Event/Listener/.
4. **Rendering** — `Rendering/ElementLowering` turns the derived stored forest into the rendered forest. See Rendering/.
5. **Finishing** — `ContentPipeline` runs the [finishing steps](docs/principles/README.md#glossary), then dispatches `RenderedTreeFinalizationEvent` over the finished rendered tree in FULL and SKELETON mode. See Event/Listener/.

The step order, the passes inside preparation, and the checks between them are owned by [docs/pipeline-steps.md](docs/pipeline-steps.md); [docs/data-flow.md](docs/data-flow.md) diagrams the data flow.

## Key Classes

Module root:
- `ContentPipeline` - Orchestrates steps 3-5 of the rendering pipeline; receives the loaded `RenderableLayout` from the route
- `RenderableLayout` - Loaded layout handed to the pipeline: a `LayoutReference` plus its `list<StoredElement>`
- `LayoutReference` - Immutable layout identity
- `ResolvedContentLayout` - Resolver output: layout ID plus the `RenderingSpecification`
- `ContentSection` - Enum of the sections
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

**Framework-owned (stays here, `#[Package('framework')]`):** Pipeline, render step (`Rendering/`) and data loaders (`Hydration/`), field serializers, cache, events, output formats, generic loaders, tagged locator consumers, route loader, type introspection schema.

**DI registration follows the class.** Tagged services (`content_system.data_loader`, `content_system.config_serializer`, `content_system.entity_specification_source`) are resolved via `tagged_locator`/`tagged_iterator` at compile time, regardless of which DI file defines them.

## Naming

Consult [`NAMING.md`](NAMING.md) before adding or renaming a type.

## Administration API

Admin-facing endpoints are documented in [Api/README.md](Api/README.md).

## Subdirectories

- **Adapter/** - [Adapter/README.md](Adapter/README.md) - Specification sources, layout assignment entities, resolution helpers
- **Api/** - [Api/README.md](Api/README.md) - Admin API controllers (layout preview, resolve-and-diagnose, the draft mutation actions, and the persisted mutation actions)
- **Binding/** - [Binding/README.md](Binding/README.md) - Binding specification system: declarations wiring a type's reference properties to loaders and seeding its primitive inputs, authored inline or synthesized automatically from a `resolvedBy` reference property and fill-applied at scaffold/replace with no client action. Explicit application goes via the `bind-element` mutation or an `insert-element` carrying a `bindingSpecificationId`
- **Cache/** - [Cache/README.md](Cache/README.md) - HTTP cache integration and invalidation
- **Diagnostics/** - [Diagnostics/README.md](Diagnostics/README.md) - Layout analysis: per-element property resolution plus a well-formedness/resolvability report
- **Event/** - [Event/README.md](Event/README.md) - Rendering lifecycle event definitions, and [Event/Listener/README.md](Event/Listener/README.md) for the listeners on them
- **Helper/** - Utility classes (ContentLayoutMetadataDeriver)
- **Hydration/** - [Hydration/README.md](Hydration/README.md) - The data-loading half of the render step: `DataLoader/` data fetching plus the remaining `DataContext/` utilities; the render step itself lives in Rendering/
- **Layout/** - [Layout/README.md](Layout/README.md) - Element tree, entities, field types, scaffolding, element type system, universal style options
- **Mutation/** - [Mutation/README.md](Mutation/README.md) - Server-side structural layout edits (insert, remove, move, replace, duplicate, wrap, unwrap, attach, bind), each re-resolved through the diagnostics pass. They apply statelessly to a draft tree or commit to a stored layout
- **Output/** - [Output/README.md](Output/README.md) - Response formatting and partial rendering
- **Rendering/** - [Rendering/README.md](Rendering/README.md) - The pre-render wiring step on stored elements (context-wiring validation, redistribute derivation), then the render step: data loading, context distribution, and building the rendered tree
- **Resolution/** - [Resolution/README.md](Resolution/README.md) - Property-resolution kernel (element/context resolvers, resolution candidates)
- **SalesChannel/** - [SalesChannel/README.md](SalesChannel/README.md) - Store API endpoints
- **Schema/** - Data loader type introspection and schema generation
- **Validation/** - [Validation/README.md](Validation/README.md) - DAL write-time resolvability gate (`PreWriteValidationEvent` validators)
- **Storefront/ContentSystem/** - [Storefront/ContentSystem/README.md](../../../Storefront/ContentSystem/README.md) - Header and footer sections, which are Storefront-owned.

## Reference Documents

- [docs/README.md](docs/README.md) - Index of the module's reference documents, one subject per file
- [docs/principles/README.md](docs/principles/README.md) - Why the module's design rules hold, and what was decided against
