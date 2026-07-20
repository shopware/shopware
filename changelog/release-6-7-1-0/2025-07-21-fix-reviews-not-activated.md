---
title: Fix CMS element loading when reviews are not activated
---
# Core
* Changed `\Shopware\Core\Content\Product\Cms\ProductDescriptionReviewsCmsElementResolver` to check if reviews are enabled before actually loading the reviews.
___
# Storefront
* Changed `\Shopware\Storefront\Controller\CmsController` to check if reviews are enabled before actually loading the reviews.
