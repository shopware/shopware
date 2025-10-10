# ContentSystem

Runtime content routing and layout rendering. Routes stored in DB, not container config, because merchants create them through the admin UI. Routes can be assigned to specific sales channels.

Three-phase architecture:

1. **Routing**: URL → content route → entity IDs (Routing/)
2. **Layout Resolution**: Route + context → content layout (Routing/LayoutResolution/)
3. **Hydration**: Layout + data requirements → populated content tree (Hydration/)

Store API entry point (`/store-api/content/{path}`) orchestrates full pipeline in SalesChannel/.

## Why DB-Based Routes

Can't use Symfony's standard route registration because routes are runtime data. Merchants create/edit/delete routes through admin UI. ContentRouter queries `content_route` table per request and builds a secondary Symfony RouteCollection for pattern matching. Cache this in production or performance will be unacceptable.

## Data Flow

```
URL → ContentRouter → EntityIdResolver → LayoutResolver → RefinedLayoutBuilder → ContentElementHydrator → Response
```

Routes contain URL patterns (`/product/{seoUrl}`) with parameter bindings that map placeholders to entities. EntityIdResolver queries entities based on these bindings. LayoutResolver determines which content layout to use (static assignment or dynamic cascade lookup). Layout is refined and hydrated with data before rendering.

## Key Classes

- `ContentRouter` - Entry point, delegates to RouteCollectionBuilder + ContentRouteMatcher (Routing/Router/)
- `EntityIdResolver` - URL parameters → entity IDs via DB queries (Routing/IdResolution/)
- `LayoutResolver` - Cascade-based layout lookup (Routing/LayoutResolution/)
- `ContentElement` - Tree structure with slots, data requirements, context (Layout/Element/)
- `LayoutRefinery` - Single-pass refinement, no recursive placeholders (Layout/Refinery/)
- `ContentElementHydrator` - Loads data + resolves context (Hydration/)
- `ContentRoute` - Store API endpoint, orchestrates pipeline (SalesChannel/)

## Subdirectories

- Routing/: URL matching, entity resolution, layout resolution
- Layout/: Element tree structure, type system, refinement
- Hydration/: Data loading, context distribution
- SalesChannel/: Store API endpoint
