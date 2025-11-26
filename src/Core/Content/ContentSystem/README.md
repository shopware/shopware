# ContentSystem

Runtime content rendering for CMS-capable entities. Supports direct layout rendering for Products, Categories, and Landing Pages with sales channel-specific layout assignments.

## Entity-Based Rendering

Products, Categories, and Landing Pages can have content layouts assigned directly in the database with sales channel specificity.

**Endpoint:** `/store-api/content/{path}`

**Supported path patterns:**
- `product/{productId}` - Direct product rendering (handled by ProductContentLayoutContextFactory)
- `category/{categoryId}` - Direct category rendering (handled by CategoryContentLayoutContextFactory)
- `landing-page/{landingPageId}` - Direct landing page rendering (handled by LandingPageContentLayoutContextFactory)

Pipeline:
1. **Layout Resolution**: Entity ID + sales channel → content layout assignment
2. **Load**: Layout ID → ContentLayoutEntity
3. **PreHydration Events**: Layout preparation (placeholder resolution, virtual root wrapping)
4. **Hydration**: Layout + data requirements → populated content tree
5. **PostHydration Events**: Layout finalization (virtual root cleanup, partial extraction)

## Data Flow

```
Entity ID → ContextFactory → RenderingSpecification → LayoutLoader
  → PreContentHydrationEvent → ContentElementHydrator → AfterContentHydrationEvent → Response
```

Direct entity-to-layout lookup. Entity-specific factories query assignment tables (`product_content_layout`, `category_content_layout`, `landing_page_content_layout`) with sales channel fallback (specific → global). Factory creates RenderingSpecification with entity ID as placeholder. Layout is loaded, prepared via events, hydrated, and finalized via events before response.

## Key Classes

### Entity Factories
- `ProductContentLayoutContextFactory` - Product ID + sales channel → specification (Adapter/)
- `CategoryContentLayoutContextFactory` - Category ID + sales channel → specification (Adapter/)
- `LandingPageContentLayoutContextFactory` - Landing page ID + sales channel → specification (Adapter/)
- `EntityLayoutResolver` - Shared layout resolution logic (Adapter/FactoryHelper/)

### Rendering Pipeline
- `RenderingSpecification` - Complete rendering specification (layout ID + placeholders + request + target element)
- `PlaceholderValues` - Immutable map of placeholder values
- `LayoutLoader` - Loads ContentLayoutEntity from repository (Layout/Loader/)
- `ContentElement` - Tree structure with slots, data requirements, context (Layout/Element/)
- `ContentElementHydrator` - Loads data + resolves context (Hydration/)
- `ContentPipeline` - Pipeline orchestrator (module root)
- `RenderingSpecificationResolver` - Factory selection via Chain of Responsibility (module root)
- `ContentRoute` - Store API endpoints (SalesChannel/)

## Architecture

### Factory Pattern

The rendering pipeline (events → hydration → events) is independent of the data source. Context factories extending `AbstractRenderingSpecificationFactory` translate entity IDs into `RenderingSpecification`. Pipeline receives specification and renders content. This enables:
- Chain of Responsibility pattern - factories tried in priority order
- Future: Email-based, preview-based, admin-based rendering
- Clean separation: entity concerns in factories, rendering in pipeline

## Subdirectories

- Layout/: Element tree structure, component system, scaffolding
- Hydration/: Data loading, context distribution
- SalesChannel/: Store API endpoints
- Adapter/: Entity adaptation for CMS-capable entities (Product, Category, Landing Page)
