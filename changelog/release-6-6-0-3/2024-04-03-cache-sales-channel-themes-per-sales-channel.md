---
title: Cache SalesChannel-themes per SalesChannel
issue: NEXT-34783
---
# Storefront
* Changed `\Shopware\Storefront\Theme\DatabaseSalesChannelThemeLoader::load` to cache the resolved themes per sales channel, to fix issue with some snippets not being available, if those were not cached.
