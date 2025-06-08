---
title: Added wishlist cookie acceptance check before adding item into wishlist
issue: NEXT-11785
---
# Storefront
*  Added a new Storefront route `wishlist/add-after-login/<product-id>` in `\Shopware\Storefront\Controller\WishlistController::addAfterLogin` that allow to add a product into wishlist after login.
*  Added cookie enabled conditional check in Storefront's `WishlistLocalStoragePlugin.add` method, if the cookie is not set, the page will redirect to `wishlist/add-after-login/<product-id>` route.
