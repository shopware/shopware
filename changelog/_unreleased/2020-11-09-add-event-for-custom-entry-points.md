---
title: Add event for custom entry points
issue: /
author: Rune Laenen
author_email: rune@laenen.nu 
author_github: @runelaenen
---
# Core
*  Added `Shopware\Core\Content\Category\Event\SalesChannelEntryPointsEvent`
*  Changed `Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder` to use `SalesChannelEntryPointsEvent` instead of hardcoded possibilities. 
*  Changed `Shopware\Core\Content\Category\SalesChannel\NavigationRoute` to use `SalesChannelEntryPointsEvent` instead of hardcoded possibilities. 
*  Changed `Shopware\Core\Content\Sitemap\Provider\CategoryUrlProvider` to use `SalesChannelEntryPointsEvent` instead of hardcoded possibilities. 
*  Changed `Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute` to use `SalesChannelEntryPointsEvent` instead of hardcoded possibilities. 
