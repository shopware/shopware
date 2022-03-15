---
title: Fix wishlists on checkout pages
issue: NEXT-20167
author: Lennart Tinkloh
author_email: l.tinkloh@shopware.com
author_github: @lernhart
---
# Storefront
* Added the wishlist widget to the minimal header to allow wishlist functionality on all pages.
* Added block `layout_header_minimal_wishlist` in `Storefront/Resources/views/storefront/layout/header/header-minimal.html.twig` when the config `core.cart.wishlistEnabled` is on.
* Added param `showCounter` to `Storefront/Resources/views/storefront/layout/header/actions/wishlist-widget.html.twig` to control, whether to render an icon with the wishlist enabled.
* Added option `showCounter` to `Storefront/Resources/app/storefront/src/plugin/header/wishlist-widget.plugin.js` to control, whether to render the counter of wished items on the wishlist.
