---
title: Don't remove cache cookies for 404 pages
issue: NEXT-36927
---
# Core
* Changed `\Shopware\Core\Framework\Adapter\Cache\Http\CacheResponseSubscriber` to not remove cache cookies for 404 pages. This prevents logged in customers getting delivered cached pages for not logged-in customers after a 404 page, because the cache cookies were deleted.

