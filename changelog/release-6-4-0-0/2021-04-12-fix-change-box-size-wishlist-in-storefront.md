---
title: Fix change box size of wishlist items in storefront
issue: NEXT-14342
---
# Storefront
* Deprecated `{% block component_product_box_currently_not_available %}` in `views/storefront/component/product/card/box-wishlist.html.twig`.
* Changed `{% block component_product_box_delivery_time %}` in `views/storefront/component/product/card/box-wishlist.html.twig`, removed hard-coded delivery handling and replaced it with new include template `delivery-information.html.twig`.
* Added new template file `delivery-information.html.twig` in `views/storefront/component/wishlist`.
