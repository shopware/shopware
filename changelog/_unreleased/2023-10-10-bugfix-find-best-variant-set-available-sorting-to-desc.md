---
title: Fix find best variant sorting
issue: NEXT-29904
author: Alexander Kludt
author_email: coding@aggrosoft.de
---
# Core
* Changed `\Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute` to use ascending sorting on product.available field, makes sure to get an available variant as best variant
