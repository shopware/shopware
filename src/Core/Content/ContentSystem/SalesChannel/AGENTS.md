@README.md

## Source Code References

**Main Content Routes:**
- `ContentRoute` - Full format endpoint implementation
- `AbstractContentRoute` - Full format decorator base
- `ContentRouteResponse` - Full format response wrapper
- `ContentDecomposedRoute` - Decomposed format endpoint implementation
- `AbstractContentDecomposedRoute` - Decomposed format decorator base
- `ContentDecomposedRouteResponse` - Decomposed format response wrapper
- `ContentSkeletonRoute` - Skeleton format endpoint implementation
- `AbstractContentSkeletonRoute` - Skeleton format decorator base
- `ContentSkeletonRouteResponse` - Skeleton format response wrapper
- `ContentDataRoute` - Data format endpoint implementation
- `AbstractContentDataRoute` - Data format decorator base
- `ContentDataRouteResponse` - Data format response wrapper

**Header Routes (Header/):**
- `ContentHeaderRoute`, `ContentHeaderDecomposedRoute`, `ContentHeaderSkeletonRoute`, `ContentHeaderDataRoute`

**Footer Routes (Footer/):**
- `ContentFooterRoute`, `ContentFooterDecomposedRoute`, `ContentFooterSkeletonRoute`, `ContentFooterDataRoute`

## Constraints

### Endpoint Details

**Full Format:**
- **Path**: `/store-api/content/{path}`
- **Methods**: GET
- **Returns**: `ContentPage` (full element trees)
- **HTTP Cache**: Enabled (`_httpCache: true`)

**Decomposed Format:**
- **Path**: `/store-api/content-decomposed/{path}`
- **Methods**: GET
- **Returns**: `ContentDecomposedPage` (skeletons + data + assignments)
- **HTTP Cache**: Enabled (`_httpCache: true`)

**Skeleton Format:**
- **Path**: `/store-api/content-skeleton/{path}`
- **Methods**: GET
- **Returns**: `ContentSkeletonPage` (elements without hydrated data)
- **HTTP Cache**: Enabled (`_httpCache: true`)

**Data Format:**
- **Path**: `/store-api/content-data/{path}`
- **Methods**: GET
- **Returns**: `ContentDataPage` (data + assignments only)
- **HTTP Cache**: Enabled (`_httpCache: true`)

All wildcards: `{path}` matches any URL pattern

### Request Parameters

Full and decomposed endpoints accept optional parameters via query string:
- `elementId`: Request specific element subtree only

## Quick Reference

- **Main Endpoints** (entity-based resolution):
  - `/store-api/content/{path}` → `ContentPage` (full format)
  - `/store-api/content-decomposed/{path}` → `ContentDecomposedPage` (decomposed format)
  - `/store-api/content-skeleton/{path}` → `ContentSkeletonPage` (skeleton format)
  - `/store-api/content-data/{path}` → `ContentDataPage` (data format)
- **Header Endpoints** (domain-aware resolution): `/store-api/content-header`, `-decomposed`, `-skeleton`, `-data`
- **Footer Endpoints** (domain-aware resolution): `/store-api/content-footer`, `-decomposed`, `-skeleton`, `-data`
- **404s**: Throw `ContentSystemException` with specific codes
- **HTTP cache**: Enabled, cached by sales channel + URL + customer group
- **Extension**: Decorate `Abstract*Route` classes
- **Response formats**:
  - `ContentPage`: layoutId, elements (array), layoutName, layoutVersion
  - `ContentDecomposedPage`: layoutId, skeletons, data, assignments, layoutName, layoutVersion
  - `ContentSkeletonPage`: layoutId, elements (array), layoutName, layoutVersion
  - `ContentDataPage`: layoutId, data, assignments, layoutName, layoutVersion
