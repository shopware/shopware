# Caching

The rules in this area state when the HTTP cache may store a render and which component records the cache tags of its values.

## The HTTP cache stores a render only when every value it shows has a recorded covering tag

A cache tag covers a value when every change of that value invalidates the tag.
The module marks a render that shows one value without a covering tag as uncacheable.
`RenderingCacheContext::disable()` sets that mark on the [cache context](README.md#glossary), and no later result reverses it.
An empty result is a value.
An element renders differently when its loader found nothing, and that difference depends on what the loader requested.
The empty result of a lookup therefore needs a covering tag like a loaded value does.
A loader that returns with no input needs no tag of its own, because the layout tags of the render cover the stored layout.
A loader result states its cache dependency separately from whether it carries data.
For a tag that the platform defines, the module relies on the invalidation of the platform.
When the platform defines a tag for a value and a change of that value does not invalidate it, the platform has a defect.
For a tag that the module defines, the module invalidates the tag on every change of the value.

Why: The HTTP cache stores the whole response.
A write removes the stored response before it expires only by invalidating a recorded tag or by clearing the whole cache.
A change of a value without a covering tag can leave every recorded tag valid, so the stored response can outlive the change.
The HTTP cache has no unit smaller than the response.
One value without a covering tag therefore makes the whole render uncacheable.

Decided against: Caching the render and relying on the expiry time of the cache entry.
A tag that only the most frequent change of the value invalidates.
A delete, a translation write or a write that skips the indexer can then leave the response in the cache.

In code:

- `ElementDataResolver::processCacheTags()` calls `RenderingCacheContext::disable()` for a loader result that carries no cache tag list.
- `ContentDataLoaderResult::noInput()` returns a cache-aware result without data and with an empty tag list for a loader that read nothing outside the stored layout.
- `ContentDataLoaderResult::layoutOnly()` returns the value that depends only on the stored layout, in a cache-aware result with an empty tag list.
- `ContentDataLoaderResult::foundNothing()` returns a result without data after its own lookup that found nothing or threw a caught `ShopwareHttpException`, with tags covering the request.
- `ContentDataLoaderResult::foundNothingUncacheable()` returns a result without data and without a cache tag list after that same lookup when no tag covers the request.
- `CacheFinalizer::finalize()` sets `PlatformRequest::ATTRIBUTE_HTTP_CACHE` to `false` on the request when the cache context is disabled.
- `EntityCacheTagResolver::resolve()` returns null for an entity without a tag pattern.
- `EntityLoader::load()` returns `ContentDataLoaderResult::uncacheable($entity)` for a loaded entity when `EntityCacheTagResolver::resolve()` returns null.
- `EntityCacheTagResolver::resolveRelated()` returns the parent's product tag and the manufacturer tag of a loaded product, each when the product has that id.
- `ElementDataResolverTest::testUncacheableResultDisablesTheCacheContext` pins that a loader result without a cache tag list disables the cache context.
- `ElementDataResolverTest::testDisableIsNotReversedByALaterCacheAwareResult` pins that a later result with tags leaves the cache context disabled.
- `CacheFinalizerTest::testFinalizeSetsCacheDisabledAttribute` pins that a disabled cache context sets the request attribute to `false`.
- `EntityCacheTagResolverTest::testReturnsNullForUnsupportedEntity` pins the null for an entity without a tag pattern.
- `EntityLoaderTest::testLoadReturnsUncacheableWhenCacheTagResolverReturnsNull` pins the uncacheable result of `EntityLoader::load()`.
- `EntityLoaderTest::testLoadTagsTheRequestedIdWhenEntityNotFoundInRepository` and `testLoadTagsTheRequestedIdWhenRepositoryThrows` pin that a lookup without an entity records the tag of the requested id on a result without data.
- `EntityCacheTagResolverTest::testResolvesRelatedTags`, `EntityLoaderTest::testLoadRecordsRelatedProductTags` and `EntityCollectionLoaderTest::testLoadRecordsRelatedTagsOfEveryEntityWithoutDuplicates` pin that a loaded product records its parent's and manufacturer's tags.
- `EntityCollectionLoaderTest::testLoadTagsEveryRequestedIdWhenRepositoryThrows` pins that a lookup that throws records the tag of every requested id on a result without data.
- `EntityLoaderTest::testLoadReturnsNoInputWhenPropertyInputIsUnresolved` pins that a loader that read nothing outside the stored layout records no tag.
- `ElementDataResolverTest::testFoundNothingUncacheableDisablesTheCacheContext` pins that a result without data and without a cache tag list disables the cache context.
- See [Cache/README.md](../../Cache/README.md#cache-tag-patterns).

## The component that adds a value to a render records its tag or disables the cache context

A data loader states the cache dependency of its data in the `ContentDataLoaderResult` it returns.
A mapping source provider, a registry override of `resolveAdmittedSource()` and a listener write to the `RenderingCacheContext` they receive.
A specification source returns the tags of its [root source](README.md#glossary) from `resolveCacheTags()`.
A loader returns `ContentDataLoaderResult::cachedExternally()` only on a path where a delegate ran.
The delegate then owns the caching of that data.
A loader returns `ContentDataLoaderResult::failedExternally()` when the delegate threw a `ShopwareHttpException`.
The module relies on the delegate and does not check or test the delegate's invalidation.

Why: Only the component that reads a value has the identity of the data it read.
No later step of the render path can detect a value that entered the render without a tag.
The invalidation of a platform route belongs to the platform.

In code:

- `ContentDataLoaderResult::cachedExternally()` returns a cache-aware result with an empty tag list.
- `ContentDataLoaderResult::failedExternally()` returns a cache-aware result with an empty tag list.
- `MappingSourceResolutionContext::$cacheContext` gives a mapping source provider the cache context of the render.
- `RenderedTreeFinalizationEvent::$cacheContext` gives a listener the cache context of the render.
- `AbstractSpecificationSource::resolveCacheTags()` returns the tags that `ContentRoute::load()` adds to the cache context.
- `CategoryMappingCandidateProviderTest::testOnlyReadingTheCategoryMediaAddsItsDependency` pins that `CategoryMappingCandidateProvider` adds the tag of a mapped media.
- `ProductVariantSelectionSubscriberTest::testOptionsFollowTheEffectiveProductInsteadOfThePageProduct` and `testAParentProductLoadsItsOwnConfiguratorSettings` pin that `ProductVariantSelectionSubscriber` adds the product tag of the variant and of its parent.
- `NavigationDataLoaderTest::testLoadReturnsFailedExternallyWhenNavigationLoaderThrows` pins that a throwing navigation loader leaves a cache-aware result without data and without a tag.
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
