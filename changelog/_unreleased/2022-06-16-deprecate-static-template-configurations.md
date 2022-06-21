---
title: Deprecate static template configurations
issue: NEXT-22056
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Storefront
* Deprecated static unused configs `seo.descriptionMaxLength`, `cms.revocationNoticeCmsPageId`, `cms.taxCmsPageId`, `cms.tosCmsPageId` and `confirm.revocationNotice`
* Changed the meta description to allow 255 characters (instead of 150 characters) in template `Resources/views/storefront/layout/meta.html.twig`, as indicated in the administration. Make sure to shorten long descriptions yourself.
