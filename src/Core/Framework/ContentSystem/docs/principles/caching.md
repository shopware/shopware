# Caching

The rules in this area state when the HTTP cache may store a render and which component records the cache tags of its values.

## The HTTP cache stores a render only when a recorded tag covers every value it shows

A cache tag covers a value when every change of that value invalidates the tag.
The module marks a render that shows one value without a covering tag as uncacheable.
`RenderingCacheContext::disable()` sets that mark on the [cache context](README.md#glossary), and no later result reverses it.
An empty result is a value.
An element renders differently when its loader found nothing, and that difference depends on what the loader requested.
The empty result of a lookup therefore needs a covering tag like a loaded value does.
A loader that returns with no lookup needs no tag of its own, because the layout tags of the render cover the stored layout.
A loader result states its cache dependency separately from whether it carries data.

Why: The HTTP cache stores the whole response.
After a change of data, only the invalidation of a recorded tag removes the stored response before it expires.
A value without a covering tag stays in the stored response after its data changed, and nothing invalidates it.
The HTTP cache has no unit smaller than the response.
One value without a covering tag therefore makes the whole render uncacheable.

Decided against: Caching the render and relying on the expiry time of the cache entry.
A tag that only the most frequent change of the value invalidates.
A delete, a translation write or a write that skips the indexer then leaves the response in the cache.

In code:

- `ElementDataResolver::processCacheTags()` calls `RenderingCacheContext::disable()` for a loader result that carries no cache tag list.
- `CacheFinalizer::finalize()` sets `PlatformRequest::ATTRIBUTE_HTTP_CACHE` to `false` on the request when the cache context is disabled.
- `EntityCacheTagResolver::resolve()` returns null for an entity without a tag pattern.
- `EntityLoader::load()` returns `ContentDataLoaderResult::uncacheable()` when `EntityCacheTagResolver::resolve()` returns null.
- `ElementDataResolverTest::testUncacheableResultDisablesTheCacheContext` pins that a loader result without a cache tag list disables the cache context.
- `ElementDataResolverTest::testDisableIsNotReversedByALaterCacheAwareResult` pins that a later result with tags leaves the cache context disabled.
- `CacheFinalizerTest::testFinalizeSetsCacheDisabledAttribute` pins that a disabled cache context sets the request attribute to `false`.
- `EntityCacheTagResolverTest::testReturnsNullForUnsupportedEntity` pins the null for an entity without a tag pattern.
- `EntityLoaderTest::testLoadReturnsUncacheableWhenCacheTagResolverReturnsNull` pins the uncacheable result of `EntityLoader::load()`.
- No test pins that the empty result of a lookup records a covering tag or disables the cache context.
- See [Cache/README.md](../../Cache/README.md#cache-tag-patterns).

## The component that adds a value to a render records its tag or disables the cache context

A data loader states the cache dependency of its data in the `ContentDataLoaderResult` it returns.
A mapping source provider, a registry override of `resolveAdmittedSource()` and a listener write to the `RenderingCacheContext` they receive.
A specification source returns the tags of its [root source](README.md#glossary) from `resolveCacheTags()`.
A loader returns `ContentDataLoaderResult::cachedExternally()` only on a path where a delegate ran.
The delegate then owns the caching of that data.
The module relies on the delegate and does not check or test the delegate's invalidation.

Why: Only the component that reads a value has the identity of the data it read.
No later step of the render path can detect a value that entered the render without a tag.
The invalidation of a platform route belongs to the platform.

In code:

- `ContentDataLoaderResult::cachedExternally()` returns a cache-aware result with an empty tag list.
- `MappingSourceResolutionContext::$cacheContext` gives a mapping source provider the cache context of the render.
- `RenderedTreeFinalizationEvent::$cacheContext` gives a listener the cache context of the render.
- `AbstractSpecificationSource::resolveCacheTags()` returns the tags that `ContentRoute::load()` adds to the cache context.
- `CategoryMappingCandidateProviderTest::testOnlyReadingTheCategoryMediaAddsItsDependency` pins that `CategoryMappingCandidateProvider` adds the tag of a mapped media.
- `ProductVariantSelectionSubscriberTest::testOptionsFollowTheEffectiveProductInsteadOfThePageProduct` and `testAParentProductLoadsItsOwnConfiguratorSettings` pin that `ProductVariantSelectionSubscriber` adds the product tag of the variant and of its parent.
- No test pins that a listener or a mapping source provider disables the cache context.
- See [custom-loaders.md](../../Hydration/DataLoader/docs/custom-loaders.md#cache-awareness).

## A write that moves a reference invalidates the tags of both targets

A write that moves a reference from one entity to another changes the renders of both entities.
The invalidation of that write invalidates the tags of the previous target and the tags of the new target.
An invalidation that needs a row the write removes reads that row before the write.

Why: An invalidation that reads the database after the write finds only the new target.
The render of the previous target then stays in the cache with the state before the write.

In code:

- `CacheInvalidationSubscriber::beforeDelete()` reads the assigned entity of a layout assignment before the delete removes the row.
- `CacheInvalidationSubscriberTest::testInvalidatesEntityCacheTagOfDeletedAssignmentOnSuccess` pins that a successful delete of a layout assignment invalidates the tag that `EntityCacheTagResolver::resolve()` returns.
- No test pins that an update of the entity id of a layout assignment invalidates the tags of the previous entity.
- See [Cache/README.md](../../Cache/README.md#invalidation).
