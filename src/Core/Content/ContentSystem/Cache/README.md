# Cache

HTTP cache integration for content system routes. Manages cache tag collection during hydration and cache invalidation when entities change.

## Key Classes

- `CacheFinalizer` - Applies accumulated cache state to HTTP response after hydration
- `CacheInvalidationSubscriber` - Invalidates cached pages when content entities change
- `EntityCacheTagResolver` - Resolves entity definitions to cache tag patterns

## Cache Flow

Cache tags flow through the rendering pipeline:

1. Route creates `RenderingCacheContext` and adds initial tags from specification
2. Data loaders return `ContentDataLoaderResult` with cache tag information
3. `ContentElementHydrator` accumulates tags in context (or disables cache for uncacheable data)
4. `CacheFinalizer` applies final state to HTTP response

## Cache Tag Resolution

`EntityCacheTagResolver` maps entity definitions to cache tag patterns:

| Entity | Tag Pattern |
|--------|-------------|
| product | `product-{id}` |
| category | `category-route-{id}` |
| landing_page | `landing-page-route-{id}` |
| cms_page | `cms-page-{id}` |
| product_stream | `product-stream-{id}` |

Unsupported entities return null, causing the page to become uncacheable.

## Cache Invalidation

`CacheInvalidationSubscriber` listens to `EntityWrittenContainerEvent` and invalidates:

- **content_layout changes** - Invalidates `content-layout-{id}` tag
- **product_content_layout changes** - Invalidates related product tags
- **category_content_layout changes** - Invalidates related category tags
- **landing_page_content_layout changes** - Invalidates related landing page tags
- **header_content_layout changes** - Invalidates layout and header route tags
- **footer_content_layout changes** - Invalidates layout and footer route tags

Assignment table changes look up the associated entity ID and invalidate that entity's cache tag. Header/footer assignment changes invalidate both the layout tag and the route-specific tag.
