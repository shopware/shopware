---
title: Use admin interface language for extension information
issue: NEXT-15586
author: Tobias Berge
author_email: t.berge@shopware.com 
author_github: @tobiasberge
---
# Core
* Added argument `Shopware\Core\Framework\Store\Services\StoreService` to `Shopware\Core\Framework\Store\Services\ExtensionLoader`
* Added argument `user.repository` to `Shopware\Core\Framework\Store\Api\ExtensionStoreDataController`
* Added argument `language.repository` to `Shopware\Core\Framework\Store\Api\ExtensionStoreDataController`
* Added new method `switchContext` to `Shopware\Core\Framework\Store\Api\ExtensionStoreDataController` in order to use the current admin language for the current context when running method `getInstalledExtensions`
* Added argument `Shopware\Core\Framework\Store\Services\StoreService` to `Shopware\Core\Framework\Store\Services\ExtensionLoader`
* Added new optional argument `(string) $locale` to method `loadFromArray` in `Shopware\Core\Framework\Store\Services\ExtensionLoader`
