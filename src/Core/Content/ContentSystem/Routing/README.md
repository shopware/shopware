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
- `LayoutResolver` - Priority-based layout assignment (LayoutResolution/)

## Route Structure

Routes contain:
- `url_pattern`: Pattern with placeholders (`/product/{seoUrl}`)
- `parameter_bindings`: Maps placeholders to entity resolution rules (`array<string, ParameterBinding>`)
- `priority`: Tie-breaker when patterns have equal specificity

Routes can be global (visible in all sales channels) or channel-specific (assigned to specific sales channels). Route visibility is determined by layout assignments: routes require at least one layout assignment (either sales-channel-specific or global with null salesChannelId) to be visible. Routes with assignments are filtered per sales channel context in RouteCollectionBuilder.

Layout assignments are stored in `content_layout_assignment` table with `route_id` foreign key. LayoutResolver evaluates assignments in priority order (DESC), matching by entity type/ID or association path. First match wins.

## Subdirectories

- Router/: Route loading and pattern matching
- IdResolution/: Parameter extraction and entity queries
- LayoutResolution/: Priority-based layout assignment
- Entity/: DAL definitions (ContentRouteDefinition, ContentRouteEntity)

## Performance

RouteCollectionBuilder queries DB per request. Cache the RouteCollection in production.
