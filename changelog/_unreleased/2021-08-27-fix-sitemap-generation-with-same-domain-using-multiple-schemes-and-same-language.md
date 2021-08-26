---
title: Fix sitemap generation with same domain using multiple schemes and same language
issue: NEXT-15353
author_github: @Dominik28111
---
# Core
* Changed method `Shopware\Core\Content\Sitemap\Service\SitemapExporter::initSitemapHandles()` to use https only if a domain has http and https.
