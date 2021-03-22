---
title: Guest wishlists are different accross different sales channels
issue: NEXT-13164
---
# Storefront
* Added a global javascript variable `window.salesChannelId` in `platform/src/Storefront/Resources/views/storefront/base.html.twig` to differentiate sales channel.
* Changed `WishlistLocalStorage` in `src/Storefront/Resources/app/storefront/src/plugin/wishlist/local-wishlist.plugin.js` to use `wishlist-<sales-channel-url>` instead of fixed key.
