---
title: Fix review display in cms product detail page
issue: NEXT-19689
---
# Core
* Changed behaviour of the following files to consider variant parents when displaying reviews, like the default Product Detail Page:
  * `src/Core/Content/Product/Cms/BuyBoxCmsElementResolver.php`
  * `src/Core/Content/Product/Cms/ProductDescriptionReviewsCmsElementResolver.php`