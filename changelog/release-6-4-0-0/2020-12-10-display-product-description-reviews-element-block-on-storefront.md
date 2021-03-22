---
title: Display product description and reviews element and block on Storefront
issue: NEXT-11745
---
# Core
* Added `Shopware\Core\Content\Product\Cms\ProductDescriptionReviewsCmsElementResolver` to resolve `product-description-reviews` cms element.
* Added `Shopware\Core\Content\Cms\SalesChannel\Struct\ProductDescriptionReviewsStruct.php` to handle data for `product-description-reviews` cms element.
* Added `Shopware\Core\Content\Product\SalesChannel\Review\RatingMatrix.php` to calculate review matrix
* Added `Shopware\Core\Content\Product\SalesChannel\Review\MatrixElement.php`
___
# Storefront
* Added `src/Storefront/Resources/views/storefront/component/product/description.html.twig` to display product description.
* Added `src/Storefront/Resources/views/storefront/component/product/properties.html.twig` to display product properties.
* Added `src/Storefront/Resources/views/storefront/component/review/review.html.twig` to display product reviews.
* Added `src/Storefront/Resources/views/storefront/component/review/review-form.html.twig` to display review form.
* Added `src/Storefront/Resources/views/storefront/component/review/review-item.html.twig` to display review item information.
* Added `src/Storefront/Resources/views/storefront/component/review/review-login.html.twig` to display login form
* Added `src/Storefront/Resources/views/storefront/component/review/review-widget.html.twig` to display review matrix
* Added `src/Storefront/Resources/views/storefront/element/cms-element-product-description-reviews.html.twig` to display `product-description-reviews` cms element.
* Added `src/Storefront/Resources/views/storefront/block/cms-block-product-description-reviews.html.twig` contains `product-description-reviews` element.
