# Header

Store API endpoints for header content. Four routes provide different response formats.

## Key Classes

- `AbstractContentHeaderRoute` - Abstract route base for full format
- `ContentHeaderRoute` - Full format endpoint implementation
- `ContentHeaderRouteResponse` - Full format response wrapper
- `AbstractContentHeaderDecomposedRoute` - Abstract route base for decomposed format
- `ContentHeaderDecomposedRoute` - Decomposed format endpoint implementation
- `ContentHeaderDecomposedRouteResponse` - Decomposed format response wrapper
- `AbstractContentHeaderSkeletonRoute` - Abstract route base for skeleton format
- `ContentHeaderSkeletonRoute` - Skeleton format endpoint implementation
- `ContentHeaderSkeletonRouteResponse` - Skeleton format response wrapper
- `AbstractContentHeaderDataRoute` - Abstract route base for data format
- `ContentHeaderDataRoute` - Data format endpoint implementation
- `ContentHeaderDataRouteResponse` - Data format response wrapper

## Endpoints

Header routes are singletons per domain/sales channel combination. No path parameter required.

**Full Format:** `GET /store-api/content-header`
- Returns `ContentPage` with complete element trees

**Decomposed Format:** `GET /store-api/content-header-decomposed`
- Returns `ContentDecomposedPage` with skeletons + data + assignments

**Skeleton Format:** `GET /store-api/content-header-skeleton`
- Returns `ContentSkeletonPage` without hydrated data

**Data Format:** `GET /store-api/content-header-data`
- Returns `ContentDataPage` with data + assignments only

## Layout Resolution

Uses domain-aware resolution via `HeaderSpecificationFactory`:

1. Domain + SalesChannel specific assignment
2. SalesChannel specific assignment (domain = null)
3. Global assignment (both = null)

First match wins. Throws `ContentSystemException` if no assignment found.

## HTTP Cache

All endpoints decorated with `_httpCache: true`. Invalidated when `header_content_layout` assignments change.
