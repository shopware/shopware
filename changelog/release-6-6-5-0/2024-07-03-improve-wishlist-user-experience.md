---
title: Improve wishlist user experience
issue: NEXT-37104
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Storefront
* Added `Wishlist/onLoginRedirect` event in `WishlistLocalStoragePlugin`.
* Changed `WishlistLocalStoragePlugin` to use `location.href` instead of `location.replace` when redirecting to login page to support browser back button.
* Changed `AddToWishlistPlugin` to update wishlist state on login redirect.
