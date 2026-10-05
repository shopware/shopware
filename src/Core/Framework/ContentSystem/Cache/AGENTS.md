## Constraints

- `RenderingCacheContext` created in route, passed through pipeline — tags accumulate, `disable()` is irreversible
- Supported entities: product, category, landing_page, cms_page, product_stream — all others cause uncacheable
- Invalidation triggers: `EntityWrittenContainerEvent` for content_layout + all 5 assignment tables
- Table names come from the definitions' `ENTITY_NAME`; the two Storefront-owned section tables arrive via the
  `shopware.content_system.section_assignment_entities` parameter, never as a literal in Core
