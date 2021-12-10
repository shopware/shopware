---
title: Load product reviews of all variants when using CMS element
author: Vincent Neubauer
author_email: v.neubauer@vonmaehlen.com
author_github: dallyger
---
# Storefront
* Update `Shopware\Core\Content\Product\Cms\ProductDescriptionReviewsCmsElementResolverController` to use
  `$product->getParentId()` if available when loading reviews.
