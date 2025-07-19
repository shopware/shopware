---
title: Cleanup internals of ProductSliderCmsElementResolver
issue: NEXT-39765
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Changed the internals of `Shopware\Core\Content\Product\Cms\ProductSliderCmsElementResolver` for better readability and simplified code
* Deprecated `Shopware\Core\Content\Product\Cms\ProductSliderCmsElementResolver::PRODUCT_ASSOCIATIONS` - Will be removed, as the associations will not be loaded in the collect method anymore
