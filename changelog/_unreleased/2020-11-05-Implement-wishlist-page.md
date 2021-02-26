---
title: Implement wishlist page in storefront
issue: NEXT-11281
---
# Storefront
*  Added `Shopware\Storefront\Controller\WishlistController`.
*  Added `Shopware\Storefront\Page\Wishlist\WishlistPage`.
*  Added new page loader `Shopware\Storefront\Page\Wishlist\WishlistPageLoader` to load `Shopware\Storefront\Page\Wishlist\WishlistPage`.
*  Added a new event `Shopware\Storefront\Page\Wishlist\WishlistPageLoaderEvent` to be fired after `Shopware\Storefront\Page\Wishlist\WishlistPage` is loaded.
*  Added new wishlist page `Shopware\Storefront\Resources\views\storefront\page\wishlist\index.html.twig`.
*  Added new wishlist pagelet `Shopware\Storefront\Resources\views\storefront\page\wishlist\wishlist-pagelet.html.twig`.
*  Added new wishlist element `Shopware\Storefront\Resources\views\storefront\element\cms-element-wishlist-listing.html.twig`.
*  Added new wishlist component listing `Shopware\Storefront\Resources\views\storefront\component\wishlist\listing.html.twig` to override block `element_product_listing_sorting`.
*  Added new wishlist component action `Shopware\Storefront\Resources\views\storefront\component\wishlist\card\action.html.twig` to override block `component_product_box_action_detail`.
*  Added new wishlist box product `Shopware\Storefront\Resources\views\storefront\component\product\card\box-wishlist.html.twig` to override `box-standard.html.twig`.
