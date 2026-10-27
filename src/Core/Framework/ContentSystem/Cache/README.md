# Cache

HTTP cache integration for content system routes. Manages cache tag collection during hydration and invalidation when entities change.

## Key Classes

- `CacheFinalizer` - Applies accumulated cache state to HTTP response after hydration
- `CacheInvalidationSubscriber` - Invalidates cached pages when content entities change
- `EntityCacheTagResolver` - Resolves entity definitions to cache tag patterns
- `RenderingCacheContext` - Tracks tags + disabled state through the pipeline

## Cache Tag Patterns

| Entity         | Tag Pattern               |
|----------------|---------------------------|
| product        | `product-{id}`            |
| category       | `category-route-{id}`     |
| landing_page   | `landing-page-route-{id}` |
| cms_page       | `cms-page-{id}`           |
| product_stream | `product-stream-{id}`     |

Unsupported entities return null → page becomes uncacheable.

## Invalidation

`CacheInvalidationSubscriber` listens to `EntityWrittenContainerEvent`:
- **content_layout** → `content-layout-{id}`
- **assignment tables** (product/category/landing_page/header/footer) → looks up associated entity and invalidates its tag

Every table name comes from its definition's `ENTITY_NAME`. Header and footer are Storefront-owned, so
the Storefront hands those two to the subscriber through the container parameter
`shopware.content_system.section_assignment_entities`; Core declares it empty.

Custom-field mapping metadata uses a separate dependency: `content-system-custom-field-mapping-metadata`.
It is attached only when a render attempts a custom-field mapping, including an unavailable field, and applies
to both whole-field and inline mappings. Writes/deletes to custom-field definitions, sets, or set relations
invalidate those pages. Layouts with only normal core mappings receive no such dependency and remain cached.
The custom-field provider owns path recognition; general rendering code does not inspect custom-field names.
