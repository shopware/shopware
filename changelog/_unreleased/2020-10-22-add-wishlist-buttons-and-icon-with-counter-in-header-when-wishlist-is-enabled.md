---
title: Add wishlist buttons and icon with counter in the header when wishlist is enabled
issue: NEXT-11308
---
# Storefront
*  Added `Shopware\Storefront\Controller\WishlistController`.
*  Added a new svg icon `heart-fill` in `src/Storefront/Resources/app/storefront/dist/assets/icon/default/heart-fill.svg`.
*  Added new wishlist header's widget template in `src/Storefront/Resources/views/storefront/layout/header/actions/wishlist-widget.html.twig` to show wishlist icon in the header.
*  Added a new block `layout_header_actions_wishlist` in `src/Storefront/Resources/views/storefront/layout/header/header.html.twig` to show wishlist widget.
*  Added a new block `base_script_wishlist_state` in `src/Storefront/Resources/views/storefront/base.html.twig` to declare some wishlist global config which being used in the wishlist plugins.
*  Added new WishlistWidgetPlugin `Resources/app/storefront/src/plugin/header/wishlist-widget.plugin.js` which render the total number of products in the wishlist.
*  Added new BaseWishlistStoragePlugin `Resources/app/storefront/src/plugin/wishlist/base-wishlist-storage.plugin.js` which provide some common methods for 2 new wishlist storage plugins.
*  Added new WishlistLocalStoragePlugin `Resources/app/storefront/src/plugin/wishlist/local-wishlist-storage.plugin.js` which registered when customer not logged in, it will help store customer's wishlist products in the LocalStorage.
*  Added new WishlistPersistStoragePlugin `Resources/app/storefront/src/plugin/wishlist/persist-wishlist-storage.plugin.js` which registered when customer logged in, it will help store customer's wishlist products in the Database.
*  Added new AddToWishlistPlugin `Resources/app/storefront/src/plugin/wishlist/add-to-wishlist.plugin.js` which provide the function to toggle a product item in/out the wishlist.
*  Added a new wishlist toggle button component in ` src/Storefront/Resources/views/storefront/component/product/card/wishlist.html.twig`.
*  Added a new block `component_product_box_wishlist` in `src/Storefront/Resources/views/storefront/component/product/card/box-standard.html.twig` to render wishlist toggle button.
*  Added a new block `page_checkout_item_wishlist` in `src/Storefront/Resources/views/storefront/page/checkout/checkout-item.html.twig` to render wishlist toggle button.
*  Added a new block `page_product_detail_wishlist` in ` src/Storefront/Resources/views/storefront/page/product-detail/buy-widget.html.twig` to render wishlist toggle button.
