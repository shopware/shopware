# Routing

@README.md

## Source Code References

- `ContentRouter` (Router/) - Entry point, orchestrates matching
- `RouteCollectionBuilder` (Router/) - DB query, builds Symfony RouteCollection
- `ContentRouteMatcher` (Router/) - Pattern matching via Symfony UrlMatcher
- `EntityIdResolver` (IdResolution/) - URL params → entity IDs
- `LayoutResolver` (LayoutResolution/) - Cascade-based layout lookup

## Quick Reference

- **Pattern matching**: Specificity first, then priority (DESC)
- **Parameter types**: Resolution (has `resolution` field) vs Passthrough (no `resolution` field)
- **Caching**: MANDATORY for production performance
- **Priority**: Distinct values for potentially conflicting routes
- **URL parameters**: Scalar values only
- **Route structure**: `url_pattern`, `parameter_binding`, `priority`, `layout_id` OR `layout_cascade`
