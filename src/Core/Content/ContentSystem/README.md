# ContentSystem

Runtime content rendering with two access patterns: route-based (via URL routing) and entity-based (adapting CMS-capable entities). Supports direct layout rendering for Products and Categories.

## Two Rendering Modes

### Route-Based Rendering

Traditional URL-based content routing. Routes stored in DB (not container config) because merchants create them through admin UI. Routes can be assigned to specific sales channels.

**Endpoint:** `/store-api/content/{path}`

Five-phase pipeline:
1. **Route Matching**: URL → matched content route
2. **Entity Resolution**: URL parameters → entity IDs
3. **Layout Resolution**: Route + context → content layout
4. **Refinement**: Layout + resolved data → refined layout
5. **Hydration**: Refined layout + data requirements → populated content tree

### Entity-Based Rendering (NEW)

Adapts CMS-capable entities to ContentSystem rendering. Products and Categories can have content layouts assigned directly in database with sales channel specificity, bypassing URL routing infrastructure.

**Endpoint:** `/store-api/content/{path}`

**Supported path patterns:**
- `product/{productId}` - Direct product rendering (handled by ProductContextFactory)
- `category/{categoryId}` - Direct category rendering (handled by CategoryContextFactory)

Three-phase pipeline:
1. **Layout Resolution**: Entity ID + sales channel → content layout (via `LayoutSearchHelper` with entity-specific factories)
2. **Refinement**: Layout + placeholder values → refined layout
3. **Hydration**: Refined layout + data requirements → populated content tree

**Key Difference:** No routing infrastructure needed. Direct entity → layout → rendered content.

## Why DB-Based Routes

Can't use Symfony's standard route registration because routes are runtime data. Merchants create/edit/delete routes through admin UI. ContentRouter queries `content_route` table per request and builds a secondary Symfony RouteCollection for pattern matching. Cache this in production or performance will be unacceptable.

## Data Flow

### Route-Based Flow

```
URL → ContentRouter → EntityIdResolver → LayoutResolver → RouteBasedContextFactory
  → RenderingSpecification → RefinedLayoutBuilder → ContentElementHydrator → Response
```

Routes contain URL patterns (`/product/{seoUrl}`) with parameter bindings that map placeholders to entities. EntityIdResolver queries entities based on these bindings. LayoutResolver determines which content layout to use via priority-based assignment matching. RouteBasedContextFactory dissolves routing concepts into RenderingSpecification. Layout is refined and hydrated with data before rendering.

### Entity-Based Flow

```
Entity ID → ProductContextFactory/CategoryContextFactory → LayoutSearchHelper
  → RenderingSpecification → RefinedLayoutBuilder → ContentElementHydrator → Response
```

Direct entity-to-layout lookup. ProductContextFactory/CategoryContextFactory use LayoutSearchHelper to query `product_content_layout` or `category_content_layout` table with sales channel fallback (specific → global). Factory creates RenderingSpecification with entity ID as placeholder. Same rendering pipeline as route-based, but no routing infrastructure involved.

## Key Classes

### Route-Based
- `ContentRouter` - URL → matched route (Routing/Router/)
- `EntityIdResolver` - URL parameters → entity IDs (Routing/IdResolution/)
- `LayoutResolver` - Route + context → layout ID (Routing/LayoutResolution/)
- `RouteBasedContextFactory` - Creates specification from routing (Routing/)

### Entity-Based (NEW)
- `ProductContextFactory` - Product ID + sales channel → specification (Adapter/)
- `CategoryContextFactory` - Category ID + sales channel → specification (Adapter/)
- `LayoutSearchHelper` - Shared query logic with sales channel fallback (Adapter/)

### Shared Rendering Pipeline
- `RenderingSpecification` - Complete rendering specification (layout ID + placeholders + target element)
- `PlaceholderValues` - Immutable map of placeholder values
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
- Layout/: Element tree structure, type system, refinement
- Hydration/: Data loading, context distribution
- SalesChannel/: Store API endpoints
- Adapter/: Entity adaptation for CMS-capable entities (Product, Category)
