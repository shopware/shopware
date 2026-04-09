---
title: Move review active check into store api routes
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Changed `Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewRoute::load` to throw an exception if the product reviews have been disabled for the corresponding sales channel, to make it consistent with the `Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewSaveRoute::save` method
