---
title: Put items to wishlist from products listing and detail pages.
issue: NEXT-11280
---
# Core
*  Added new route `/store-api/v{version}/customer/wishlist` which return the customer wishlist data.
*  Added new route `/store-api/v{version}/customer/wishlist/delete/{productId}` which delete the product from the wishlist of customer
*  Added new route `/store-api/v{version}/customer/wishlist/add/{productId}` which add the product into the wishlist of customer
*  Added event `CustomerWishlistLoaderCriteriaEvent`, The event will dispatch criteria when loader customer wishlist data.
*  Added event `CustomerWishlistLoaderCriteriaEvent`, The event will dispatch criteria when loader customer wishlist data.
*  Added an exception `CustomerWishlistNotFoundException` which will be thrown when do not exist data customer wishlist in the database.
*  Added an exception `DuplicateWishlistProductException` which will be thrown when the user added a product which has been added before.
*  Added an exception `WishlistProductNotFoundException` which will be thrown when the user deleted a product that does not exist in their wishlist.
___
