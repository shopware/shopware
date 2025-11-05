# SalesChannel

@README.md

## Source Code References

- `ContentRoute` - Full format endpoint implementation
- `AbstractContentRoute` - Full format decorator base
- `ContentRouteResponse` - Full format response wrapper
- `ContentDecomposedRoute` - Decomposed format endpoint implementation
- `AbstractContentDecomposedRoute` - Decomposed format decorator base
- `ContentDecomposedRouteResponse` - Decomposed format response wrapper
- `ContentRouteLoader` - Pipeline orchestrator (shared by both routes)

## Constraints

### Three-Phase Pipeline Orchestration

Both routes use Chain of Responsibility for factory selection. ContentRouteLoader then orchestrates these phases sequentially:

1. **Factory Selection**: Iterate context factories (ProductContextFactory, CategoryContextFactory, RouteBasedContextFactory, etc.) in DI priority order until one returns RenderingSpecification
2. **Refinement**: RefinedLayoutBuilder builds layout, LayoutRefinery refines
3. **Hydration**: ContentElementHydrator loads data + resolves context

See `ContentRoute::load()` and `ContentDecomposedRoute::load()` for factory iteration. See `ContentRouteLoader::load()` for pipeline implementation.

### Endpoint Details

**Full Format:**
- **Path**: `/store-api/content/{path}`
- **Methods**: GET, POST
- **Returns**: `ContentPage` (full element trees)
- **HTTP Cache**: Enabled (`_httpCache: true`)

**Decomposed Format:**
- **Path**: `/store-api/content-decomposed/{path}`
- **Methods**: GET, POST
- **Returns**: `DecomposedContentPage` (skeletons + data + assignments)
- **HTTP Cache**: Enabled (`_httpCache: true`)

Both wildcards: `{path}` matches any URL pattern

### Query Parameters

Both endpoints support:
- `?elementId=xyz`: Request specific element subtree only

## Quick Reference

- **Endpoints**:
  - `/store-api/content/{path}` → `ContentPage` (full format)
  - `/store-api/content-decomposed/{path}` → `DecomposedContentPage` (decomposed format)
- **Pipeline**: Factory Selection → Refinement → Hydration (MUST be sequential)
- **Chain of Responsibility**: Factories tried in DI priority order, first non-null wins
- **404s**: Throw `ContentSystemException` with specific codes
- **HTTP cache**: Enabled, cached by sales channel + URL + customer group
- **Extension**: Decorate `AbstractContentRoute` or `AbstractContentDecomposedRoute`, or add new context factory
- **Response formats**:
  - `ContentPage`: layoutId, elements (array), layoutName, layoutVersion
  - `DecomposedContentPage`: layoutId, skeletons, data, assignments, layoutName, layoutVersion
