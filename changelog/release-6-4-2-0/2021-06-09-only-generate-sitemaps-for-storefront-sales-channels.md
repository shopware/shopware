---
title: Only generate sitemaps for storefront sales channels
issue: NEXT-15496
---
# Core
* Changed command `sitemap:generate` to only generate sitemaps for sales channels of type storefront
* Changed `\Shopware\Core\Content\Sitemap\ScheduledTask\SitemapGenerateTaskHandler` to only generate sitemaps for sales channels of type storefront
