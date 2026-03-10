---
title: Cache default category levels
issue: #11163
---
# Core
* Added `\Shopware\Core\Content\Category\Service\DefaultCategoryLevelLoader` to load all default category levels and cache the result for the current sales channels main navigation.
* Added `\Shopware\Core\Content\Category\Event\CategoryLevelLoaderCacheKeyEvent` to be able to influence the cache key for the default category level loader.
* Changed `\Shopware\Core\Content\Category\SalesChannel\NavigationRoute` to use the default category level loader to load all default levels.
___

# Upgrade Information

## Added caching to the `NavigationRoute`

The navigation route now caches the default category levels for the current sales channel's main navigation. 
This improves performance by reducing the need to repeatedly load and hydrate the same category levels on every page.

When your navigation is dynamic, you need to subscribe to the `CategoryLevelLoaderCacheKeyEvent` to add the necessary information to the cache tag, so your dynamic content is displayed properly.