---
title: Remove core dependencies from ProductPageLoader
issue: NEXT-21968
author: Stefan Sluiter
author_email: s.sluiter@shopware.com
author_github: ssltg
---
# Storefront
* Removed `SalesChannelCmsPageRepository`, `CmsSlotsDataResolver` and `ProductDefinition` as a constructor argument from `Shopware\Storefront\Page\Product\ProductPageLoader`. These where never called because the cmsPAgeResolution is already made in the `ProductDetailRoute`.
___
# Core
* Deprecated `Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageRepository`. Will be removed in v6.5.0.