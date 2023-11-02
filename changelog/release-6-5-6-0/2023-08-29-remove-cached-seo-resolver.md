---
title: Remove cached seo resolver
issue: NEXT-30236
---

# Core

* Deprecated `\Shopware\Core\Content\Seo\CachedSeoResolver` and removed the Redis caching for SEO url paths. 
  * Optimized the query in the actual SeoResolver to use always the index at the MySQL server.
