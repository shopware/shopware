# SalesChannel

@README.md

## Source Code References

- `ContentRoute` - Main endpoint implementation
- `AbstractContentRoute` - Decorator base
- `ContentRouteResponse` - Response wrapper
- `ContentPage` (Struct/) - Response payload

## Constraints

### Five-Phase Pipeline Orchestration

ContentRoute orchestrates these phases sequentially:

1. **Route Matching**: ContentRouter matches URL to content route
2. **Entity Resolution**: EntityIdResolver extracts parameters, queries entities
3. **Layout Resolution**: LayoutResolver determines layout (static or cascade)
4. **Refinement**: RefinedLayoutBuilder builds layout, LayoutRefinery refines
5. **Hydration**: ContentElementHydrator loads data + resolves context

See `ContentRoute::load()` for implementation.

### Endpoint Details

- **Path**: `/store-api/content/{path}`
- **Methods**: GET, POST
- **HTTP Cache**: Enabled (`_httpCache: true`)
- **Wildcard**: `{path}` matches any URL pattern

## Quick Reference

- **Endpoint**: `/store-api/content/{path}` (GET, POST)
- **Pipeline**: Routing → Resolution → Layout → Refinement → Hydration (MUST be sequential)
- **404s**: Throw `ContentSystemException` with specific codes
- **HTTP cache**: Enabled, cached by sales channel + URL + customer group
- **Extension**: Decorate `AbstractContentRoute`
- **Response**: `ContentPage` with rootElement, resolvedData, route, layout
