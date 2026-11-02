> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- `RenderingCacheContext` created in route, passed through pipeline — tags accumulate, `disable()` is irreversible
- Supported entities: product, category, landing_page, cms_page, product_stream — all others cause uncacheable
- Invalidation triggers: `EntityWrittenContainerEvent` for content_layout + all 5 assignment tables
- `category.media` mappings record the actual media ID through `MappedMediaCacheInvalidator::buildName()`. Its scoped invalidator handles media/translation writes and deletions plus indexing, independently of remote thumbnails and cache-rework flags; ordinary category text adds no media dependency. See [README.md](README.md).
- Table names come from the definitions' `ENTITY_NAME`; the two Storefront-owned section tables arrive via the
  `shopware.content_system.section_assignment_entities` parameter, never as a literal in Core
