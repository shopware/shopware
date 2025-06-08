---
title: Wishlist disabled details button doesn't make sense
issue: NEXT-14795
---
# Core
* Changed method `loadProducts` in `Shopware\Core\Checkout\Customer\SalesChannel\LoadWishlistRoute` to add a filter.
___
# Storefront
* Changed method `createCriteria` in `Shopware\Storefront\Pagelet\Wishlist\GuestWishlistPageletLoader.php` to add a filter.
* Changed block `component_product_box_action` in `src/Storefront/Resources/views/storefront/component/product/card/box-wishlist.html.twig` to inherit from `box-standard.html.twig`.
* Deprecated `src/Storefront/Resources/views/storefront/component/wishlist/card` from 6.5.0 due to unused.
