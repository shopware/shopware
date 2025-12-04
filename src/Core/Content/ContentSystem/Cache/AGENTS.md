# Cache

@README.md

## Source Code References

- `CacheFinalizer` - Applies cache state to HTTP response
- `CacheInvalidationSubscriber` - EntityWrittenContainerEvent subscriber
- `EntityCacheTagResolver` - Maps entity definitions to cache tag patterns

## Quick Reference

- **Entry point**: Routes call `CacheFinalizer::finalize()` after hydration
- **Context object**: `RenderingCacheContext` (module root) tracks tags and disabled state
- **Invalidation trigger**: `EntityWrittenContainerEvent` for content_layout and assignment entities
- **Supported entities**: product, category, landing_page, cms_page, product_stream
- **Unsupported entity**: Returns null from resolver, disables page caching
