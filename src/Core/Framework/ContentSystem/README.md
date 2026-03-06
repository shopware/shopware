# ContentSystem

A data-driven layout system for serving structured content through the Store API. Layouts define element trees with data requirements and context distribution, rendered through an event-driven pipeline.

## Content Sections

Three content sections with different resolution strategies:

**Main** (`/store-api/content/{path}`) — Entity-based rendering for Products, Categories, and Landing Pages. Layouts assigned per entity with sales channel fallback. See Adapter/.

**Header** (`/store-api/content-header*`) — Domain-aware singleton per domain/sales channel. Three-tier fallback: domain+channel → channel → global. See Adapter/.

**Footer** (`/store-api/content-footer*`) — Same domain-aware resolution as header.

Header and Footer are pure Storefront concepts — the Core has no knowledge of them. This is intentional: headless deployments without the Storefront bundle operate without header/footer sections. The Storefront module registers its own entity definitions, specification sources, and section resolvers via `content-system.xml`.

Each section supports four response formats: full, decomposed, skeleton, and data. See SalesChannel/ and Output/.

## Rendering Pipeline

The pipeline is source-independent — specification sources translate entity IDs into `RenderingSpecification` objects, and `ContentPipeline` renders without knowing the original data source.

1. **Specification Resolution** — Route calls `RenderingSpecificationResolver` (Adapter/) which iterates sources via `supports()` check, then assembles the specification. See Adapter/.
2. **Layout Loading** — `LayoutLoader` retrieves `ContentLayoutEntity` from repository.
3. **PreHydration Events** — Listeners prepare layout: placeholder resolution, virtual root wrapping, partial rendering pruning. See Event/Listener/.
4. **Hydration** (FULL mode only) — `ContentElementHydrator` loads data per element's requirements, then distributes context. Skipped in SKELETON mode. See Hydration/.
5. **PostHydration Events** — Listeners finalize: virtual root cleanup, partial extraction. See Event/Listener/.

## Key Classes

Module root:
- `ContentPipeline` - Orchestrates steps 2-5 of the rendering pipeline
- `ContentSection` - Enum: HEADER, FOOTER, MAIN
- `RenderingSpecification` - Layout ID, data requirements, placeholders, request, target element, cache tags
- `RenderingMode` - Enum: FULL (hydrate), SKELETON (structure only)
- `PlaceholderValues` - Immutable placeholder value map
- `SpecificationData` - Bundles data requirements + placeholders from layout resolution

## Domain Placement

Domain-specific content system classes live in their owning domain module — not centralized here. Both the class and its DI registration belong to the domain.

**Domain-owned:** Entity definitions, specification sources, data loaders, config serializers. These are co-located with the domain entity they serve (e.g., product data loader lives in the product module).

**Framework-owned (stays here):** Pipeline, hydration engine, field serializers, cache, events, output formats, generic loaders, tagged locator consumers, route loader.

**DI registration follows the class.** Tagged services (`content_system.data_loader`, `content_system.config_serializer`, `content_system.context_factory`) are resolved via `tagged_locator`/`tagged_iterator` at compile time, regardless of which XML file defines them.

## Subdirectories

- **Adapter/** - Specification sources, layout assignment entities, resolution helpers
- **Cache/** - HTTP cache integration and invalidation
- **Event/** - Hydration lifecycle event definitions
- **Event/Listener/** - Pre/post hydration pipeline transformations
- **Helper/** - Utility classes (ContentLayoutMetadataDeriver)
- **Hydration/** - Data loading and context distribution
- **Layout/** - Element tree, entities, field types, scaffolding
- **Output/** - Response formatting and partial rendering
- **SalesChannel/** - Store API endpoints
