# SalesChannel

Store API entry point for content system. Four routes provide different response formats: full, decomposed, skeleton, and data.

## Key Classes

- `AbstractContentRoute` - Abstract route base for full format
- `ContentRoute` - Full format endpoint implementation
- `ContentRouteResponse` - Full format response wrapper
- `AbstractContentDecomposedRoute` - Abstract route base for decomposed format
- `ContentDecomposedRoute` - Decomposed format endpoint implementation
- `ContentDecomposedRouteResponse` - Decomposed format response wrapper
- `AbstractContentSkeletonRoute` - Abstract route base for skeleton format
- `ContentSkeletonRoute` - Skeleton format endpoint implementation
- `ContentSkeletonRouteResponse` - Skeleton format response wrapper
- `AbstractContentDataRoute` - Abstract route base for data format
- `ContentDataRoute` - Data format endpoint implementation
- `ContentDataRouteResponse` - Data format response wrapper
- `ContentRouteLoader` - Pipeline orchestrator (shared)
- `RenderingSpecificationResolver` - Resolves path to RenderingSpecification via factories

## Endpoints

Four endpoints share pipeline but differ in response format:

**Full Format:** `GET /store-api/content/{path}?elementId={id}`
- Returns `ContentPage` with complete element trees (properties embedded)
- Simpler client integration, larger payloads

**Decomposed Format:** `GET /store-api/content-decomposed/{path}?elementId={id}`
- Returns `ContentDecomposedPage` with skeletons + deduplicated data + assignments
- Optimized for deduplication, requires client reconstruction

**Skeleton Format:** `GET /store-api/content-skeleton/{path}`
- Returns `ContentSkeletonPage` with element trees without hydrated data
- Skips hydration phase; useful for layout preview or client-side hydration

**Data Format:** `GET /store-api/content-data/{path}`
- Returns `ContentDataPage` with deduplicated data + assignments
- Requires client to have skeleton; useful for data refresh

## Request Parameters

Full and decomposed endpoints accept optional parameters via query string:
- `elementId`: String - Request only specific element and its descendants (partial rendering)

## Pipeline Orchestration

All routes delegate to context factories via Chain of Responsibility pattern to create RenderingSpecification. ContentRouteLoader then orchestrates:

1. **Factory Selection**: Iterate context factories in DI priority order, first non-null RenderingSpecification wins
2. **Load**: LayoutLoader loads ContentLayoutEntity from repository
3. **PreHydration Events**: Subscribers prepare layout (placeholder resolution, virtual root wrapping, partial pruning)
4. **Hydration**: ContentElementHydrator loads data + resolves context
5. **PostHydration Events**: Subscribers finalize layout (virtual root cleanup, partial extraction)

If `elementId` query parameter is present, partial rendering subscribers prune before hydration and extract after.

**Response Format Difference:**
- ContentRoute returns `ContentPage` directly (full element trees)
- ContentDecomposedRoute calls `ContentPage::getContentDecomposedPage()` for decomposed format
- ContentSkeletonRoute uses `RenderingMode::SKELETON` to skip hydration, returns `ContentSkeletonPage`
- ContentDataRoute calls `ContentPage::getContentDataPage()` for data-only format

All response types contain `layoutId`, `layoutName`, `layoutVersion`. Format-specific fields:
- `ContentPage`: `elements` (hydrated trees)
- `ContentDecomposedPage`: `skeletons`, `data`, `assignments`
- `ContentSkeletonPage`: `elements` (non-hydrated trees)
- `ContentDataPage`: `data`, `assignments`

## HTTP Cache

All endpoints decorated with `_httpCache: true`. Response cached based on sales channel, URL, customer group. Invalidation happens when:
- Content layouts modified
- Assigned entities modified
- Entity assignments modified

Cache tags propagated from entity queries during hydration.

## Error Handling

Returns 404 if:
- No context factory accepts the path
- Entity not found
- Layout assignment not found for entity

ContentSystemException thrown with specific error codes.
