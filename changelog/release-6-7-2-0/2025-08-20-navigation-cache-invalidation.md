---
title: Fix navigation cache invalidation
issue: 11420
---
# Core
* Changed `\Shopware\Core\Content\Category\SalesChannel\NavigationRoute::load()` and `\Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber::invalidateNavigationRoute()` to tag all navigation routes with the same tag and invalidate the cached navigations when navigation relevant data changes.
* Deprecated `\Shopware\Core\Content\Category\SalesChannel\NavigationRoute::buildName()` as navigation routes won't be tagged with dynamic tags anymore, use `NavigationRoute::ALL_TAG` instead.
___ 
# Upgrade Information
## Deprecated `NavigationRoute::buildName()`
The method `\Shopware\Core\Content\Category\SalesChannel\NavigationRoute::buildName()` is deprecated and will be removed in the next major version. It was used to build a dynamic tag name for navigation routes, but now all navigation routes are tagged with the same tag `NavigationRoute::ALL_TAG`.
___ 
# Next Major Version Changes
## Removal of `NavigationRoute::buildName()`
The method `\Shopware\Core\Content\Category\SalesChannel\NavigationRoute::buildName()` was removed, navigation routes are now only tagged with `NavigationRoute::ALL`.