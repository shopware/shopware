# Footer

@README.md

## Source Code References

- `ContentFooterRoute` - Full format endpoint
- `AbstractContentFooterRoute` - Full format decorator base
- `ContentFooterRouteResponse` - Full format response wrapper
- `ContentFooterDecomposedRoute` - Decomposed format endpoint
- `AbstractContentFooterDecomposedRoute` - Decomposed format decorator base
- `ContentFooterDecomposedRouteResponse` - Decomposed format response wrapper
- `ContentFooterSkeletonRoute` - Skeleton format endpoint
- `AbstractContentFooterSkeletonRoute` - Skeleton format decorator base
- `ContentFooterSkeletonRouteResponse` - Skeleton format response wrapper
- `ContentFooterDataRoute` - Data format endpoint
- `AbstractContentFooterDataRoute` - Data format decorator base
- `ContentFooterDataRouteResponse` - Data format response wrapper

## Constraints

### No Partial Rendering

Footer routes do not support `elementId` parameter. Footers are always rendered in full.

### Domain Resolution Priority

Resolution order (first match wins):
1. `domain_id = X AND sales_channel_id = Y`
2. `domain_id = NULL AND sales_channel_id = Y`
3. `domain_id = NULL AND sales_channel_id = NULL`

## Quick Reference

- **Endpoints**: `/store-api/content-footer`, `-decomposed`, `-skeleton`, `-data`
- **Resolution**: Domain-aware (not path-based)
- **Factory**: `FooterSpecificationFactory` (bypasses Chain of Responsibility)
- **Cache tag**: `content-footer-layout-{layoutId}`
- **Extension**: Decorate `AbstractContentFooter*Route`
