# SalesChannel

Store API entry point for content system. Two routes provide different response formats: full and decomposed.

## Key Classes

- `AbstractContentRoute` - Abstract route base for full format
- `ContentRoute` - Full format endpoint implementation
- `AbstractContentDecomposedRoute` - Abstract route base for decomposed format
- `ContentDecomposedRoute` - Decomposed format endpoint implementation
- `ContentRouteLoader` - Pipeline orchestrator (shared)
- `ContentRouteResponse` - Full format response wrapper
- `ContentDecomposedRouteResponse` - Decomposed format response wrapper

## Endpoints

Two endpoints share pipeline but differ in response format:

**Full Format:** `GET|POST /store-api/content/{path}?elementId={id}`
- Returns `ContentPage` with complete element trees (properties embedded)
- Simpler client integration, larger payloads

**Decomposed Format:** `GET|POST /store-api/content-decomposed/{path}?elementId={id}`
- Returns `DecomposedContentPage` with skeletons + deduplicated data + assignments
- Optimized for deduplication, requires client reconstruction

## Query Parameters

Both endpoints accept optional query parameters:
- `elementId`: String - Request only specific element and its descendants (partial rendering)

## Pipeline Orchestration

Both routes delegate to context factories via Chain of Responsibility pattern to create RenderingSpecification. ContentRouteLoader then orchestrates six phases:

1. **Factory Selection**: Iterate context factories in DI priority order, first non-null RenderingSpecification wins
2. **Load**: LayoutLoader loads ContentLayoutEntity from repository
3. **Scaffold**: ScaffoldingProcessor wraps layout structure
4. **Refinement**: RefinedLayoutBuilder refines layout, LayoutRefinery applies refiners
5. **Hydration**: ContentElementHydrator loads data + resolves context
6. **Dismantle**: ScaffoldingProcessor unwraps scaffolding to restore original structure

If `elementId` query parameter is present, partial rendering happens in two phases: PartialRenderingRefiner prunes before hydration, SubTreeExtractor extracts after dismantle.

**Response Format Difference:**
- ContentRoute returns `ContentPage` directly (full element trees)
- ContentDecomposedRoute calls `ContentPage::getDecomposedContentPage()` for decomposed format

Both response types contain:
- `layoutId`: Layout UUID
- `elements` or `skeletons`: Hydrated ContentElement trees (root elements)
- `layoutName`: Layout name
- `layoutVersion`: Layout version ID

## HTTP Cache

Both routes decorated with `_httpCache: true`. Response cached based on sales channel, URL, customer group. Invalidation happens when:
- Content routes modified
- Content layouts modified
- Assigned entities modified

Cache tags propagated from entity queries during hydration.

## Error Handling

Returns 404 if:
- No route matches URL
- Entity resolution finds no entity
- Layout resolution finds no layout

ContentSystemException thrown with specific error codes.

## Extension Points

Decorate `AbstractContentRoute` or `AbstractContentDecomposedRoute` to modify pipeline (add logging, validation, transformations). Don't break pipeline order - phases must run sequentially.
