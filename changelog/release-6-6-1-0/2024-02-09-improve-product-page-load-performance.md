---
title: Improve product page load performance
issue: NEXT-33741
author: Benjamin Wittwer
author_email: dev@a-k-f.de
author_github: akf-bw
---
# Core
* Changed the `checkVariantListingConfig` method in `ProductDetailRoute` to use the direct database connection to fetch only necessary data
