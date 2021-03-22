---
title: Fix guest wishlist do not show product after shopping
issue: NEXT-13643
---
# Storefront
* Changed `\Shopware\Storefront\Controller\WishlistController::index` and `guestPagelet` to load wishlist guest page and pagelet for guest customer.
* Added new storefront plugin `AccountGuestAbortButtonPlugin` in `src/Storefront/Resources/app/storefront/src/plugin/header/account-guest-abort-button.plugin.js` to publish a `guest-logout` event when guest logout button clicked.
* Added an event listener in `src/Storefront/Resources/app/storefront/src/plugin/wishlist/local-wishlist.plugin.js` to clear wishlist products on aborting guest session.

