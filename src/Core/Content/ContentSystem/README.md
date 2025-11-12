# ContentSystem

Runtime content rendering for CMS-capable entities. Supports direct layout rendering for Products, Categories, and Landing Pages with sales channel-specific layout assignments.

## Entity-Based Rendering

Products, Categories, and Landing Pages can have content layouts assigned directly in the database with sales channel specificity.

**Endpoint:** `/store-api/content/{path}`

**Supported path patterns:**
- `product/{productId}` - Direct product rendering (handled by ProductContentLayoutContextFactory)
- `category/{categoryId}` - Direct category rendering (handled by CategoryContentLayoutContextFactory)
- `landing-page/{landingPageId}` - Direct landing page rendering (handled by LandingPageContentLayoutContextFactory)

Six-phase pipeline:
1. **Layout Resolution**: Entity ID + sales channel → content layout assignment
2. **Load**: Layout ID → ContentLayoutEntity
3. **Scaffold**: Wrap layout structure for rendering
4. **Refinement**: Layout + placeholder values → refined layout
5. **Hydration**: Refined layout + data requirements → populated content tree
6. **Dismantle**: Unwrap scaffolding to restore original structure

## Data Flow

```
Entity ID → ProductContentLayoutContextFactory/CategoryContentLayoutContextFactory/LandingPageContentLayoutContextFactory
  → RenderingSpecification → LayoutLoader → ScaffoldingProcessor → RefinedLayoutBuilder
  → ContentElementHydrator → ScaffoldingProcessor → Response
```

Direct entity-to-layout lookup. Entity-specific factories query assignment tables (`product_content_layout`, `category_content_layout`, `landing_page_content_layout`) with sales channel fallback (specific → global). Factory creates RenderingSpecification with entity ID as placeholder. Layout is loaded, scaffolded, refined, hydrated, and dismantled before rendering.

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
- `ScaffoldingProcessor` - Orchestrates scaffolder execution (Layout/Scaffolding/)
- `ContentElement` - Tree structure with slots, data requirements, context (Layout/Element/)
- `LayoutRefinery` - Single-pass refinement, no recursive placeholders (Layout/Refinery/)
- `ContentElementHydrator` - Loads data + resolves context (Hydration/)
- `ContentRouteLoader` - Pipeline orchestrator (SalesChannel/)
- `ContentRoute` - Store API endpoints (SalesChannel/)

## Architecture

### Factory Pattern

The rendering pipeline (refinement → hydration → output) is independent of the data source. Context factories implementing `RenderingSpecificationFactoryInterface` translate entity IDs into `RenderingSpecification`. Pipeline receives specification and renders content. This enables:
- Chain of Responsibility pattern - factories tried in priority order
- Future: Email-based, preview-based, admin-based rendering
- Clean separation: entity concerns in factories, rendering in pipeline

## Subdirectories

- Layout/: Element tree structure, component system, refinement, scaffolding
- Hydration/: Data loading, context distribution
- SalesChannel/: Store API endpoints
- Adapter/: Entity adaptation for CMS-capable entities (Product, Category, Landing Page)
