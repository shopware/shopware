---
title: Fix endless loop in sitemap generation
issue: NEXT-12850
---
# Core
* Changed the `\Shopware\Core\Content\Sitemap\ScheduledTask\SitemapGenerateTaskHandler` to not loop endlessly if a salesChannel has multiple domains with the same language.
