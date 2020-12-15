---
title: Implement wishlist page for guest
issue: NEXT-12808
flag: FEATURE_NEXT_10549
---
# Core
* Added a new route `/store-api/v{version}/guest/wishlist` which return the guest wishlist data.
___
# Storefront
* Added `\Shopware\Storefront\Page\Wishlist\WishlistGuestPage`.
* Added `\Shopware\Storefront\Page\Wishlist\WishlistGuestPagelet`.
* Added new page loader `\Shopware\Storefront\Page\Wishlist\WishlistGuestPageLoader` to load `Shopware\Storefront\Page\Wishlist\WishlistGuestPage` and load `Shopware\Storefront\Page\Wishlist\WishlistGuestPagelet`.
* Added a new event `\Shopware\Storefront\Page\Wishlist\WishlistGuestPageLoaderEvent` to be fired after `Shopware\Storefront\Page\Wishlist\WishlistGuestPage` is loaded.
* Added a new event `\Shopware\Storefront\Page\Wishlist\WishlistGuestPageletLoadedEvent` to be fired after `Shopware\Storefront\Page\Wishlist\WishlistGuestPagelet` is loaded.
* Removed @LoginRequired annotation in `\Shopware\Storefront\Controller\WishlistController::index` to allow rendering wishlist page for guest.
* Added new method `getProducts` in `src/Storefront/Resources/app/storefront/src/plugin/wishlist/base-wishlist-storage.plugin.js` to get all products in the storage.
* Added new storefront js plugin `WishlistGuestPagePlugin` in `src/Storefront/Resources/app/storefront/src/plugin/wishlist/wishlist-guest-page.plugin.js` to render guest's wishlist products when user is not logged in.
* Added new twig file `src/Storefront/Resources/views/storefront/page/wishlist/meta.html.twig` to override `layout_head_title_inner` to render wishlist page's title as `Your wishlist`.
* Added new block `base_head` in `src/Storefront/Resources/views/storefront/page/wishlist/index.html.twig` to render wishlist/meta.html.twig.
* Added a conditional check in `src/Storefront/Resources/views/storefront/page/wishlist/index.html.twig` to render the wishlist product listing or guest product listing plugin.
