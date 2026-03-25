# Footer

Store API endpoints for footer content. Four routes provide different response formats.

## Key Classes

- `AbstractContentFooterRoute` - Abstract route base for full format
- `ContentFooterRoute` - Full format endpoint implementation
- `ContentFooterRouteResponse` - Full format response wrapper
- `AbstractContentFooterDecomposedRoute` - Abstract route base for decomposed format
- `ContentFooterDecomposedRoute` - Decomposed format endpoint implementation
- `ContentFooterDecomposedRouteResponse` - Decomposed format response wrapper
- `AbstractContentFooterSkeletonRoute` - Abstract route base for skeleton format
- `ContentFooterSkeletonRoute` - Skeleton format endpoint implementation
- `ContentFooterSkeletonRouteResponse` - Skeleton format response wrapper
- `AbstractContentFooterDataRoute` - Abstract route base for data format
- `ContentFooterDataRoute` - Data format endpoint implementation
- `ContentFooterDataRouteResponse` - Data format response wrapper

## Endpoints

Footer routes are singletons per domain/sales channel combination. No path parameter required.

**Full Format:** `GET /store-api/content-footer`
- Returns `ContentPage` with complete element trees

**Decomposed Format:** `GET /store-api/content-footer-decomposed`
- Returns `ContentDecomposedPage` with skeletons + data + assignments

**Skeleton Format:** `GET /store-api/content-footer-skeleton`
- Returns `ContentSkeletonPage` without hydrated data

**Data Format:** `GET /store-api/content-footer-data`
- Returns `ContentDataPage` with data + assignments only

## Layout Resolution

Uses domain-aware resolution via `FooterSpecificationFactory`:

1. Domain + SalesChannel specific assignment
2. SalesChannel specific assignment (domain = null)
3. Global assignment (both = null)

First match wins. Throws `ContentSystemException` if no assignment found.

## HTTP Cache

All endpoints decorated with `_httpCache: true`. Invalidated when `footer_content_layout` assignments change.
