---
title: Fix long texts in offcanvas
issue: NEXT-12273
author: Sebastian König
author_email: s.koenig@tinect.de 
author_github: @tinect
---
# Storefront
* Changed block `component_offcanvas_product_details` of `component/checkout/offcanvas-item.html.twig` to have a static `col-7` container
* Changed style for `cart-item-details` to break words in `layout/_offcanvas-cart.scss`
