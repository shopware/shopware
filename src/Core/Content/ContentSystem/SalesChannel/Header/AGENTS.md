# Header

@README.md

## Source Code References

- `ContentHeaderRoute` - Full format endpoint
- `AbstractContentHeaderRoute` - Full format decorator base
- `ContentHeaderRouteResponse` - Full format response wrapper
- `ContentHeaderDecomposedRoute` - Decomposed format endpoint
- `AbstractContentHeaderDecomposedRoute` - Decomposed format decorator base
- `ContentHeaderDecomposedRouteResponse` - Decomposed format response wrapper
- `ContentHeaderSkeletonRoute` - Skeleton format endpoint
- `AbstractContentHeaderSkeletonRoute` - Skeleton format decorator base
- `ContentHeaderSkeletonRouteResponse` - Skeleton format response wrapper
- `ContentHeaderDataRoute` - Data format endpoint
- `AbstractContentHeaderDataRoute` - Data format decorator base
- `ContentHeaderDataRouteResponse` - Data format response wrapper

## Constraints

### No Partial Rendering

Header routes do not support `elementId` parameter. Headers are always rendered in full.

### Domain Resolution Priority

Resolution order (first match wins):
1. `domain_id = X AND sales_channel_id = Y`
2. `domain_id = NULL AND sales_channel_id = Y`
3. `domain_id = NULL AND sales_channel_id = NULL`

## Quick Reference

- **Endpoints**: `/store-api/content-header`, `-decomposed`, `-skeleton`, `-data`
- **Resolution**: Domain-aware (not path-based)
- **Factory**: `HeaderSpecificationFactory` (bypasses Chain of Responsibility)
- **Cache tag**: `content-header-layout-{layoutId}`
- **Extension**: Decorate `AbstractContentHeader*Route`
