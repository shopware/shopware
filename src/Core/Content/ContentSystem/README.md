# ContentSystem

Runtime content rendering with two access patterns: route-based (via URL routing) and entity-based (adapting CMS-capable entities). Supports direct layout rendering for Products, Categories, and Landing Pages.

## Two Rendering Modes

### Route-Based Rendering

Traditional URL-based content routing. Routes stored in DB (not container config) because merchants create them through admin UI. Routes can be assigned to specific sales channels.

**Endpoint:** `/store-api/content/{path}`

Eight-phase pipeline:
1. **Route Matching**: URL → matched content route
2. **Entity Resolution**: URL parameters → entity IDs
3. **Layout Resolution**: Route + context → content layout
4. **Load**: Layout ID → ContentLayoutEntity
5. **Scaffold**: Wrap layout structure for rendering
6. **Refinement**: Layout + resolved data → refined layout
7. **Hydration**: Refined layout + data requirements → populated content tree
8. **Dismantle**: Unwrap scaffolding to restore original structure

### Entity-Based Rendering (NEW)

Adapts CMS-capable entities to ContentSystem rendering. Products, Categories, and Landing Pages can have content layouts assigned directly in database with sales channel specificity, bypassing URL routing infrastructure.

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

**Key Difference:** No routing infrastructure needed. Direct entity → layout → rendered content.

## Why DB-Based Routes

Can't use Symfony's standard route registration because routes are runtime data. Merchants create/edit/delete routes through admin UI. ContentRouter queries `content_route` table per request and builds a secondary Symfony RouteCollection for pattern matching. Cache this in production or performance will be unacceptable.

## Data Flow

### Route-Based Flow

```
URL → ContentRouter → EntityIdResolver → LayoutResolver → RouteBasedContextFactory
  → RenderingSpecification → LayoutLoader → ScaffoldingProcessor → RefinedLayoutBuilder
  → ContentElementHydrator → ScaffoldingProcessor → Response
```

Routes contain URL patterns (`/product/{seoUrl}`) with parameter bindings that map placeholders to entities. EntityIdResolver queries entities based on these bindings. LayoutResolver determines which content layout to use via priority-based assignment matching. RouteBasedContextFactory dissolves routing concepts into RenderingSpecification. Layout is loaded, scaffolded, refined, hydrated, and dismantled before rendering.

### Entity-Based Flow

```
Entity ID → ProductContentLayoutContextFactory/CategoryContentLayoutContextFactory/LandingPageContentLayoutContextFactory
  → RenderingSpecification → LayoutLoader → ScaffoldingProcessor → RefinedLayoutBuilder
  → ContentElementHydrator → ScaffoldingProcessor → Response
```

Direct entity-to-layout lookup. Entity-specific factories query assignment tables (`product_content_layout`, `category_content_layout`, `landing_page_content_layout`) with sales channel fallback (specific → global). Factory creates RenderingSpecification with entity ID as placeholder. Same rendering pipeline as route-based, but no routing infrastructure involved.

## Key Classes

### Route-Based
- `ContentRouter` - URL → matched route (Routing/Router/)
- `EntityIdResolver` - URL parameters → entity IDs (Routing/IdResolution/)
- `LayoutResolver` - Route + context → layout ID (Routing/LayoutResolution/)
- `RouteBasedContextFactory` - Creates specification from routing (Routing/)

### Entity-Based (NEW)
- `ProductContentLayoutContextFactory` - Product ID + sales channel → specification (Adapter/)
- `CategoryContentLayoutContextFactory` - Category ID + sales channel → specification (Adapter/)
- `LandingPageContentLayoutContextFactory` - Landing page ID + sales channel → specification (Adapter/)
- `EntityLayoutResolver` - Shared layout resolution logic (Adapter/FactoryHelper/)

### Shared Rendering Pipeline
- `RenderingSpecification` - Complete rendering specification (layout ID + placeholders + target element)
- `PlaceholderValues` - Immutable map of placeholder values
- `LayoutLoader` - Loads ContentLayoutEntity from repository (Layout/Loader/)
- `ScaffoldingProcessor` - Orchestrates scaffolder execution (Layout/Scaffolding/)
- `ContentElement` - Tree structure with slots, data requirements, context (Layout/Element/)
- `LayoutRefinery` - Single-pass refinement, no recursive placeholders (Layout/Refinery/)
- `ContentElementHydrator` - Loads data + resolves context (Hydration/)
- `ContentRouteLoader` - Routing-independent pipeline orchestrator (SalesChannel/)
- `ContentRoute` - Store API endpoints (SalesChannel/)

## Architecture

### Routing Independence

The rendering pipeline (refinement → hydration → output) is completely independent of routing infrastructure. Context factories implementing `RenderingSpecificationFactoryInterface` translate different sources (URL routing, entity ID) into `RenderingSpecification`. Pipeline receives specification and renders content. This enables:
- Entity-based rendering without routing
- Chain of Responsibility pattern - factories tried in priority order
- Future: Email-based, preview-based, admin-based rendering
- Clean separation: routing concerns in factories, rendering in pipeline

## Subdirectories

- Routing/: URL matching, entity resolution, layout resolution (for route-based rendering)
- Layout/: Element tree structure, type system, refinement, scaffolding
- Hydration/: Data loading, context distribution
- SalesChannel/: Store API endpoints
- Adapter/: Entity adaptation for CMS-capable entities (Product, Category, Landing Page)
