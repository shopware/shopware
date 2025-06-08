---
title: Create DAL and migration database for wishlist.
issue: NEXT-10549
---
# Core
* Added a new table `customer_wishlist` and a mapping table `customer_wishlist_product` to stored wishlist data.
* Added `customer wishlist` entity to interact with `customer wishlist products`.
* Added OneToMany association between `customer` and `customer_wishlist`.
* Added new property `wishlists` in class `Shopware\Core\Checkout\Customer\CustomerEntity`.
* Added OneToMany association between `salechannel` and `customer_wishlist`.
* Added new property `wishlists` in class `Shopware\Core\System\SalesChannel\SalesChannelEntity`.
* Added OneToMany association between `customer_wishlist` and `customer_wishlist_product`.
* Added OneToMany association between `product` and `customer_wishlist_product`.
* Added new property `wishlists` in class `Shopware\Core\Content\Product\ProductEntity`.
