---
title: Added SEO warning notification to landing pages in admin
issue: NEXT-19420
author: Krzykawski
author_email: m.krzykawski@shopware.com
author_github: Krzykawski
---
# Administration
* Added SEO warning notification to the `sw-landing-page-detail-base` component in admin
  * only appears if no layout is assigned to the landing page
  * without an assigned layout, the SEO mapping does not work correctly and the landing pages are not found
