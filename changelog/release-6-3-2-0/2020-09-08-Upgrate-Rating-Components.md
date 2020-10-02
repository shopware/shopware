---
title: Upgrade rating components
issue: NEXT-10063
author: Marcel Brode
author_email: m.brode@shopware.com
author_github:
---
# Core
* Changed `Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewRoute.php` to aknowledge reviews active state again  
___
# Administration
* Changed `sw-review` module
    * Added sidebar and refresh button for `sw-review-list`
    * Extracted star display into new component `sw-rating-stars`, which also can e.g. work with float values
    * Fixed a display bug in `sw-review-detail`, to break to long texts in headline & description
* Removed unused snippet keys from `src/Administration/Resources/app/administration/src/module/sw-review/snippet/de-DE.json`:
    * `sw-review.detail.messageSaveSuccess`
    * `sw-review.detail.buttonSave`
    * `sw-review.detail.buttonCancel`
* Removed unused snippet keys from `src/Administration/Resources/app/administration/src/module/sw-review/snippet/en-GB.json`:
    * `sw-review.detail.messageSaveSuccess`
    * `sw-review.detail.buttonSave`
    * `sw-review.detail.buttonCancel` 
___
# Storefront
* Added float value handling for ProductReview handling of product detail page
* Deprecated in `\Shopware\Storefront\Page\Product\Review\RatingMatrix.php`:
    * `getTotalPoints()` use `getPointSum()` instead
    * `$totalPoints` use `$pointSum` instead
