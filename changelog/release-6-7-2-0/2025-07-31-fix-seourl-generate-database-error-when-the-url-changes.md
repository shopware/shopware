---
title: Fix SeoUrl generate database-error when the url changes
issue: 11619
---
# Core
* Changed `updateSeoUrls` method in `Shopware\Core\Content\Seo\SeoUrlPersister` to update seo URL only if there is no canonical URL set.
