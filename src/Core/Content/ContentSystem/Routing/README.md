# Routing

Routes stored in `content_route` table, matched at runtime. Can't use Symfony's standard route registration because merchants create routes through admin UI.

ContentRouter builds secondary Symfony RouteCollection from DB per request for pattern matching. When multiple routes match, priority determines selection. Cache compiled RouteCollection in production.

## Architecture

Three responsibilities split across subdirectories:

1. **Route Matching** (Router/): Load routes from DB, pattern match against URL
2. **ID Resolution** (IdResolution/): Extract URL parameters, query entities
3. **Layout Resolution** (LayoutResolution/): Determine which content layout to use

## Key Classes

- `ContentRouter` - Entry point, orchestrates matching (Router/)
- `RouteCollectionBuilder` - DB query, builds Symfony RouteCollection (Router/)
- `ContentRouteMatcher` - Pattern matching via Symfony UrlMatcher (Router/)
- `EntityIdResolver` - URL params → entity IDs (IdResolution/)
- `LayoutResolver` - Cascade-based layout lookup (LayoutResolution/)

## Route Structure

Routes contain:
- `url_pattern`: Pattern with placeholders (`/product/{seoUrl}`)
- `parameter_binding`: Maps placeholders to entity resolution rules
- `layout_id`: Static layout assignment (optional)
- `layout_cascade`: Dynamic layout resolution config (optional)
- `priority`: Tie-breaker when patterns have equal specificity
- `sales_channels`: Sales channel assignments (optional)

Routes can be global (visible in all sales channels) or channel-specific (assigned to specific sales channels). Routes without sales channel assignments are available across all channels. Routes with assignments are filtered per sales channel context.

Routes support static layout assignment (fixed `layout_id`) or dynamic resolution (cascade lookup in `content_layout_assignment` table based on entity + sales channel).

## Subdirectories

- Router/: Route loading and pattern matching
- IdResolution/: Parameter extraction and entity queries
- LayoutResolution/: Cascade-based layout assignment
- Entity/: DAL definitions (ContentRouteDefinition, ContentRouteEntity)
- Struct/: Data structures (RouteMatchResult)

## Performance

RouteCollectionBuilder queries DB per request. Cache the RouteCollection in production.
