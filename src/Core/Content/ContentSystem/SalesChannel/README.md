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

## Pipeline

Routes delegate to `ContentPipeline` (module root) which orchestrates layout loading, event dispatch, and hydration. Each route transforms the resulting `ContentPage` to its response format.

Partial rendering via `?elementId` parameter is handled by event subscribers in the pipeline.

**Response Format Transformation:**
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

All endpoints decorated with `_httpCache: true`. Response cached based on sales channel, URL, customer group.

Each route creates a `RenderingCacheContext` that accumulates cache tags during hydration. After hydration, `CacheFinalizer` applies the final state:
- If cache disabled (uncacheable data loaded), sets `ATTRIBUTE_HTTP_CACHE` to false
- Otherwise, adds accumulated tags to response via `CacheTagCollector`

Invalidation happens when:
- Content layouts modified (`content-layout-{id}` tag)
- Assigned entities modified (entity-specific tags)
- Entity assignments modified (looks up entity and invalidates its tag)

See Cache/ directory for `CacheInvalidationSubscriber` implementation.

## Error Handling

Returns 404 if:
- No context factory accepts the path
- Entity not found
- Layout assignment not found for entity

ContentSystemException thrown with specific error codes.

## Subdirectories

Header and footer content have dedicated endpoints in subdirectories. These routes are singletons (no `{path}` parameter) and use domain-aware resolution instead of entity-based resolution.

- **Header/** - Header content endpoints (`/store-api/content-header*`)
- **Footer/** - Footer content endpoints (`/store-api/content-footer*`)
