---
title: Implemented wishlist merge local storage
issue: NEXT-11282
---
# Storefront
* Added method `ajaxMerge` in `Shopware\Storefront\Controller\WishlistController` to merge products on wishlist from anonymous users to registered users.
* Added method `ajaxPagelet` in `Shopware\Storefront\Controller\WishlistController` to get products on the wishlist of customer.
* Added private method `_merge` in WishlistPersistStoragePlugin `Resources/app/storefront/src/plugin/wishlist/persist-wishlist-storage.plugin.js` to merge products on wishlist from anonymous users to registered users.
* Added private method `_pagelet` in WishlistPersistStoragePlugin `Resources/app/storefront/src/plugin/wishlist/persist-wishlist-storage.plugin.js` to get products on the wishlist of customer. 
