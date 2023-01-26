---
title: Reset translator's cache after switching theme
issue: NEXT-23923
---
# Storefront
* Changed `\Shopware\Storefront\Theme\CachedResolvedConfigLoaderInvalidator::assigned` to invalidate translator cache after switching themes of a sales channel
* Changed `\Shopware\Core\Framework\Adapter\Translation\TranslatorCacheInvalidate::clearCache` to use `CacheInvalidator::invalidate` instead of `CacheItemPoolInterface::deleteItem`
* Changed `\Shopware\Core\Framework\Adapter\Translation\Translator::loadSnippets` to consider `salesChannelId` in cache key when caching translator
