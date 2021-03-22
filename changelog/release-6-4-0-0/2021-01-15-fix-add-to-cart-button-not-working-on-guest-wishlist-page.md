---
title: Fix add to cart button not working on guest wishlist page
issue: NEXT-13151
---
# Storefront
* Changed function `loadProductListForGuest` of storefront plugin `GuestWishlistPagePlugin` to reinitialize `AddToCart` plugin after loaded.
