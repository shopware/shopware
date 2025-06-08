---
title:              Custom links entry in seoUrl table
issue:              NEXT-13630
author_github:      @hungmac-sw
---
# Core
* Added a condition to the query builder of `getCategoryChildren` method in `src/Storefront/Framework/Seo/SeoUrlRoute/SeoUrlUpdateListener` to not create the seoUrl with the category type is `link`
