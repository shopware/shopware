---
title: Wishlist merge/local storage
issue: NEXT-11282
---
# Core
*  Added new route `/store-api/v{version}/customer/wishlist/merge` which merges the wishlist products from the anonymous users to the registered users.
*  Added event `WishlistMergedEvent`, The event will dispatch wishlist products that have been merged.
___
