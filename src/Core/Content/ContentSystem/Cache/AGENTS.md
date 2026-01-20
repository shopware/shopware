# Cache

@README.md

## Source Code References

- `CacheFinalizer` - Applies cache state to HTTP response
- `CacheInvalidationSubscriber` - EntityWrittenContainerEvent subscriber
- `EntityCacheTagResolver` - Maps entity definitions to cache tag patterns

## Quick Reference

- **Entry point**: Routes call `CacheFinalizer::finalize()` after hydration
- **Context object**: `RenderingCacheContext` (module root) tracks tags and disabled state
- **Invalidation triggers**: `EntityWrittenContainerEvent` for:
  - `content_layout` - Layout changes
  - `product_content_layout`, `category_content_layout`, `landing_page_content_layout` - Entity assignments
  - `header_content_layout`, `footer_content_layout` - Header/footer assignments
- **Supported entities**: product, category, landing_page, cms_page, product_stream
- **Unsupported entity**: Returns null from resolver, disables page caching
