---
title: Remove core dependencies from ProductCombinationFinder
issue: NEXT-21968
author: Stefan Sluiter
author_email: s.sluiter@shopware.com
author_github: ssltg
---
# Storefront
* Deprecated `Shopware\Storefront\Page\Product\Configurator\ProductCombinationFinder`. It will be removed in v6.5.0. Use `Shopware\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRoute` instead.
* Deprecated `Shopware\Storefront\Page\Product\Configurator\FoundCombination`. It will be removed in v6.5.0. Use `Shopware\Core\Content\Product\SalesChannel\FindVariant\FoundCombination` instead.
* Deprecated `Shopware\Storefront\Page\Product\Configurator\AvailableCombinationResult`. It will be removed in v6.5.0. Use `Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationResult` instead.
* Deprecated `ProductCombinationFinder` as constructor parameter in `Shopware\Storefront\Controller\ProductController`.
* Added `FindProductVariantRoute` as constructor parameter in `Shopware\Storefront\Controller\ProductController`.
* Deprecated `ProductCombinationFinder` as constructor parameter in `Shopware\Storefront\Controller\CmsController`.
* Added `FindProductVariantRoute` as constructor parameter in `Shopware\Storefront\Controller\CmsController`.
___
# Core
* Added `Shopware\Core\Content\Product\SalesChannel\FindVariant\AbstractFindProductVariantRoute`.
* Added `Shopware\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRoute`. This route is used to find the matching variant for a given option combination of a product.
* Added new store-api route `/product/{productId}/find-variant`
* Added `Shopware\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRouteResponse`.
* Added `Shopware\Core\Content\Product\SalesChannel\FindVariant\FoundCombination`.