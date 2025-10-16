# ContentSystem

Runtime content routing and layout rendering. Routes stored in DB, not container config, because merchants create them through the admin UI. Routes can be assigned to specific sales channels.

Five-phase pipeline architecture:

1. **Route Matching**: URL → matched content route (Routing/Router/)
2. **Entity Resolution**: URL parameters → entity IDs (Routing/IdResolution/)
3. **Layout Resolution**: Route + context → content layout (Routing/LayoutResolution/)
4. **Refinement**: Layout + resolved data → refined layout (Layout/Refinery/)
5. **Hydration**: Refined layout + data requirements → populated content tree (Hydration/)

Store API entry point (`/store-api/content/{path}`) orchestrates full pipeline in SalesChannel/.

## Why DB-Based Routes

Can't use Symfony's standard route registration because routes are runtime data. Merchants create/edit/delete routes through admin UI. ContentRouter queries `content_route` table per request and builds a secondary Symfony RouteCollection for pattern matching. Cache this in production or performance will be unacceptable.

## Data Flow

```
URL → ContentRouter → EntityIdResolver → LayoutResolver → RefinedLayoutBuilder → ContentElementHydrator → Response
```

Routes contain URL patterns (`/product/{seoUrl}`) with parameter bindings that map placeholders to entities. EntityIdResolver queries entities based on these bindings. LayoutResolver determines which content layout to use via priority-based assignment matching. Layout is refined and hydrated with data before rendering.

## Key Classes

- `ContentRouter` - Entry point, delegates to RouteCollectionBuilder + ContentRouteMatcher (Routing/Router/)
- `EntityIdResolver` - URL parameters → entity IDs via DB queries (Routing/IdResolution/)
- `LayoutResolver` - Priority-based layout assignment (Routing/LayoutResolution/)
- `ContentElement` - Tree structure with slots, data requirements, context (Layout/Element/)
- `LayoutRefinery` - Single-pass refinement, no recursive placeholders (Layout/Refinery/)
- `ContentElementHydrator` - Loads data + resolves context (Hydration/)
- `ContentRouteLoader` - Orchestrates pipeline (SalesChannel/)
- `ContentRoute` - Store API endpoint, delegates to loader (SalesChannel/)

## Subdirectories

- Routing/: URL matching, entity resolution, layout resolution
- Layout/: Element tree structure, type system, refinement
- Hydration/: Data loading, context distribution
- SalesChannel/: Store API endpoint
