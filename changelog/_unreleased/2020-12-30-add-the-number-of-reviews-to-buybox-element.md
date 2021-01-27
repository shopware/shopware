---
title: Add the number of reviews to Buy box element
issue: NEXT-12803
---
# Core
* Changed method `enrich` in `src/Core/Content/Product/Cms/BuyBoxCmsElementResolver` to set `TotalReviews` to `BuyBoxStruct`
___
# Storefront
* Changed the block `buy_widget_reviews` in `buy-widget` to distinct between `reviews labels`
* Changed the `id`, `href`, `aria-controls` and `aria-labelledby` of `Description and reviews` section in `cms-element-product-description-reviews` to distinct between `Description and reviews` sections
