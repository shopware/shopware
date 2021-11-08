---
title: Limit variants to sales channel
issue: NEXT-11392
flag: FEATURE_NEXT_18592
author_github: @Dominik28111
---
# Core
* Deprecated method `Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader:load()` parameter `$salesChannelId` will be mandatory in `v6.5.0`.
* Changed method `Shopware\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader::load()` to hand over sales channel id to combination loader.
___
# Storefront
* Deprecated class `Shopware\Storefront\Page\Product\Configurator\AvailableCombinationLoader` use  `Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader` instead.
