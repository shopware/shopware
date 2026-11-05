# Cache

HTTP cache integration for content system routes. Manages cache tag collection during hydration and invalidation when entities change.

## Key Classes

- `CacheFinalizer` - Applies accumulated cache state to HTTP response after hydration
- `CacheInvalidationSubscriber` - Invalidates cached pages when content entities change
- `MappedMediaCacheInvalidator` - Invalidates dependencies on mapped media after media/translation writes, deletions, and media indexing
- `EntityCacheTagResolver` - Resolves entity definitions to cache tag patterns
- `RenderingCacheContext` - Created in `ContentRoute`, passed through the pipeline; tags accumulate, `disable()` is irreversible

## Cache Tag Patterns

| Entity         | Tag Pattern               |
|----------------|---------------------------|
| product        | `product-{id}`            |
| category       | `category-route-{id}`     |
| landing_page   | `landing-page-route-{id}` |
| cms_page       | `cms-page-{id}`           |
| product_stream | `product-stream-{id}`     |

Unsupported entities return null → page becomes uncacheable.

Category `category.media` mappings additionally record `content-system-mapped-media-{id}` for the media entity actually returned by the provider. The tag is added to the caller's rendering cache context and forwarded by `CacheFinalizer`. Reading only category text does not add it, even if the loaded category also has media. This does not add generic media support to `EntityCacheTagResolver` or make standalone media loaders cacheable.

## Invalidation

`CacheInvalidationSubscriber` listens to `EntityWrittenContainerEvent`:
- **content_layout** → `content-layout-{id}`
- **assignment tables** (product/category/landing_page/header/footer) → looks up associated entity and invalidates its tag

Every table name comes from its definition's `ENTITY_NAME`. Header and footer are Storefront-owned, so
the Storefront hands those two to the subscriber through the container parameter
`shopware.content_system.section_assignment_entities`; Core declares it empty.

`MappedMediaCacheInvalidator` invalidates the corresponding per-media tags for media and media-translation writes/deletions, including direct translation operations identified by their composite primary keys. It also listens to `MediaIndexerEvent` for index-time updates. DAL handling works when remote thumbnails skip indexing and does not depend on the cache-rework flags. It uses the normal immediate/delayed invalidation policy. Other-media and text-only cached pages keep their entries.

Custom-field mapping metadata uses a separate dependency: `content-system-custom-field-mapping-metadata`. The provider attaches it only when a render attempts a mapping under a supported root's `customFields` path, including an unavailable field. Whole-field and inline mappings share this policy. Literal mapping-looking text outside supported positions creates no dependency.

Writes/deletes to custom-field definitions, sets, or set relations invalidate the tag, including relation deletions whose payload contains only an ID. This deliberately invalidates pages using any custom-field mapping when mapping metadata changes. Layouts with only normal core mappings receive no such dependency and remain cached. The provider owns path recognition; general rendering does not inspect custom-field names.

The provider's separate catalogue cache retains its `custom_field_mapping_candidates` tag. Catalogue entries and rendered pages are invalidated through their respective paths, following the normal immediate/delayed purge policy.
