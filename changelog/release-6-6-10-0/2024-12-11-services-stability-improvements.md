---
title: Services stability improvements
issue: NEXT-38322
---
___
# Storefront
* Deprecated `Shopware\Storefront\Theme\StorefrontPluginRegistry` - It will become internal and not implement `\Shopware\Storefront\Theme\StorefrontPluginRegistryInterface`
* Deprecated `Shopware\Storefront\Theme\StorefrontPluginRegistryInterface` - It will be removed without replacement
* Changed `Shopware\Storefront\Theme\StorefrontPluginRegistry` to ignore services
___
# Upgrade Information
## Internalisation of StorefrontPluginRegistry & Removal of StorefrontPluginRegistryInterface

The class `Shopware\Storefront\Theme\StorefrontPluginRegistry` will become internal and will no longer implement `Shopware\Storefront\Theme\StorefrontPluginRegistryInterface`.

The interface `Shopware\Storefront\Theme\StorefrontPluginRegistryInterface` will be removed.

Please refactor your code to not use this class & interface.
___
## Internalisation of StorefrontPluginRegistry & Removal of StorefrontPluginRegistryInterface

The class `Shopware\Storefront\Theme\StorefrontPluginRegistry` is now internal and does not implement `Shopware\Storefront\Theme\StorefrontPluginRegistryInterface`.

The interface `Shopware\Storefront\Theme\StorefrontPluginRegistryInterface` has been removed.

Please refactor your code to not use this class & interface.
