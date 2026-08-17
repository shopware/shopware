> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- `RenderingCacheContext` created in route, passed through pipeline — tags accumulate, `disable()` is irreversible
- Supported entities: product, category, landing_page, cms_page, product_stream — all others cause uncacheable
- Invalidation triggers: `EntityWrittenContainerEvent` for content_layout + all 5 assignment tables
