---
title: Add default cms layout controller
issue: NEXT-20172
---
# Core
* Added the functionality to set default cms pages for products and categories which will replace the fallback.
  * For more information on this change refer to the [according ADR](../../adr/2022-04-06-add-default-cms-layouts-to-products-and-categories.md).
* Changed `\Shopware\Core\Content\Product\Subscriber\ProductSubscriber.php` in order to set the default layout.
* Added `\Shopware\Core\Content\Category\Subscriber\CategorySubscriber.php` in order to set the default layout.
___
# Administration
* Changed files in order to do not set the cms page id to later apply the default cms page id. This affects:
  * `\Shopware\Administration\Resources\app\administration\src\module\sw-category\component\sw-category-tree\index.js`
___
# Storefront
* Deprecated fallback in `\Shopware\Storefront\Controller\ProductController::index` which applies when no cms page is given. In v6.5.0 there will be a page given all time.
* Deprecated fallback in `\Shopware\Storefront\Page\Product\ProductPageLoader::loadDefaultAdditions` which will not be used in v.6.5.0 because cms page will always be set.
* Changed `\Shopware\Storefront\Page\Product\ProductPageLoader::load` to always set cms page id if given.
* Changed `\Shopware\Storefront\Page\Product\ProductPageLoader::loadCmsPage` in order to load reviews and cross-sellings if the page was set by the subscriber.
  * Also deprecated the `$product->getCmsPageId() === null` check because cms page id will always be set.
