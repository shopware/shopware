---
title: Add language to reviews
issue: NEXT-26714
---
# Core
* Changed `Shopware\Core\Content\Product\Cms\ProductDescriptionReviewsCmsElementResolver::createReviewCriteria` to add language association on review criteria.
___
# Storefront
* Changed `Shopware\Storefront\Page\Product\Review\ProductReviewLoader::createCriteria` to add language association on review criteria.
* Added lang attribute to review content in `Storefront/Resources/views/storefront/component/review/review-item.html.twig`
* Added lang attribute to review content in `Storefront/Resources/views/storefront/page/product-detail/review/review-item.html.twig`
