---
title: Fix issue SEO url not generating anymore
issue: 10513
---
# Core
* Changed `updateSeoUrls` method in `Shopware\Core\Content\Seo\SeoUrlPersister` to set `is_canonical` and `is_modified` fields of default seo url to true if a foreignKey's seo url is deleted.
