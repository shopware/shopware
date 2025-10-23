# SalesChannel

@README.md

## Source Code References

- `ContentRoute` - Main endpoint implementation
- `ContentRouteLoader` - Pipeline orchestrator
- `AbstractContentRoute` - Decorator base
- `ContentRouteResponse` - Response wrapper

## Constraints

### Three-Phase Pipeline Orchestration

ContentRoute uses Chain of Responsibility for factory selection. ContentRouteLoader then orchestrates these phases sequentially:

1. **Factory Selection**: Iterate context factories (ProductContextFactory, CategoryContextFactory, RouteBasedContextFactory, etc.) in DI priority order until one returns RenderingSpecification
2. **Refinement**: RefinedLayoutBuilder builds layout, LayoutRefinery refines
3. **Hydration**: ContentElementHydrator loads data + resolves context

See `ContentRoute::load()` for factory iteration and `ContentRouteLoader::load()` for pipeline implementation.

### Endpoint Details

- **Path**: `/store-api/content/{path}`
- **Methods**: GET, POST
- **HTTP Cache**: Enabled (`_httpCache: true`)
- **Wildcard**: `{path}` matches any URL pattern

### Query Parameters

- `?elementId=xyz`: Request specific element subtree only

## Quick Reference

- **Endpoint**: `/store-api/content/{path}` (GET, POST)
- **Pipeline**: Factory Selection → Refinement → Hydration (MUST be sequential)
- **Chain of Responsibility**: Factories tried in DI priority order, first non-null wins
- **404s**: Throw `ContentSystemException` with specific codes
- **HTTP cache**: Enabled, cached by sales channel + URL + customer group
- **Extension**: Decorate `AbstractContentRoute` or add new context factory
- **Response**: `ContentPage` with layout, layoutId, layoutName, layoutVersion
