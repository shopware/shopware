---
title: Not found cache
issue: NEXT-22404
author: Soner Sayakci
author_email: s.sayakci@shopware.com
---

# Storefront
* Added `\Shopware\Storefront\Framework\Routing\NotFound\NotFoundSubscriber` to handle 404 pages and cache the page.
  * `\Shopware\Storefront\Framework\Routing\NotFound\NotFoundPageCacheKeyEvent` can be used to manipulate the cache key
  * `\Shopware\Storefront\Framework\Routing\NotFound\NotFoundPageTagsEvent` can be used to manipulate the cache tags

