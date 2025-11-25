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

### Pipeline Orchestration

Both endpoints use Chain of Responsibility for factory selection. ContentRouteLoader then orchestrates:

1. **Factory Selection**: Iterate context factories in DI priority order until one returns RenderingSpecification
2. **PreHydration Events**: Subscribers prepare layout (placeholder resolution, virtual root, partial pruning)
3. **Hydration**: ContentElementHydrator loads data + resolves context
4. **PostHydration Events**: Subscribers finalize layout (virtual root cleanup, partial extraction)

See `ContentRouteLoader::load()` for pipeline implementation.

### Endpoint Details

**Full Format:**
- **Path**: `/store-api/content/{path}`
- **Methods**: GET
- **Returns**: `ContentPage` (full element trees)
- **HTTP Cache**: Enabled (`_httpCache: true`)

**Decomposed Format:**
- **Path**: `/store-api/content-decomposed/{path}`
- **Methods**: GET
- **Returns**: `DecomposedContentPage` (skeletons + data + assignments)
- **HTTP Cache**: Enabled (`_httpCache: true`)

Both wildcards: `{path}` matches any URL pattern

### Request Parameters

Both endpoints accept optional parameters via query string:
- `elementId`: Request specific element subtree only

## Quick Reference

- **Endpoints**:
  - `/store-api/content/{path}` → `ContentPage` (full format)
  - `/store-api/content-decomposed/{path}` → `DecomposedContentPage` (decomposed format)
- **Pipeline**: Factory Selection → PreHydration Events → Hydration → PostHydration Events
- **Chain of Responsibility**: Factories tried in DI priority order, first non-null wins
- **404s**: Throw `ContentSystemException` with specific codes
- **HTTP cache**: Enabled, cached by sales channel + URL + customer group
- **Extension**: Decorate `AbstractContentRoute` or `AbstractContentDecomposedRoute`, or add new context factory
- **Response formats**:
  - `ContentPage`: layoutId, elements (array), layoutName, layoutVersion
  - `DecomposedContentPage`: layoutId, skeletons, data, assignments, layoutName, layoutVersion
