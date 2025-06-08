
---
title: Align price and subtotal price to the edge of Offcanvas
issue: NEXT-6963
---
# Storefront
* Changed `<dd class="col-3">` to `<dd class="col-5">` in `{% block component_offcanvas_summary_total_value %}` in `storefront/component/checkout/offcanvas-cart-summary.html.twig` to align subtotal price to the edge of Offcanvas
* Added `<strong>` element to subtotal price text in `{% block component_offcanvas_summary_total_value %}` in `storefront/component/checkout/offcanvas-cart-summary.html.twig` to make it bold
* Changed `<span class="col-3">` to `<span class="col-5">` inside `{% block component_offcanvas_summary_content_info %}` in `storefront/component/checkout/offcanvas-cart-summary.html.twig` to align subtotal price to the edge of Offcanvas
* Added `<strong>` element to shipping cost price text `{% block component_offcanvas_summary_total_value %}` in `storefront/component/checkout/offcanvas-cart-summary.html.twig` to make it bold
* Removed `col-2` inside `<div class="cart-item-remove">` of `{% block component_offcanvas_product_remove %}` in `storefront/component/checkout/offcanvas-item.html.twig` to let the `{% block component_offcanvas_product_details %}` expand all to the right
* Added class `.offcanvas-cart .cart-item-remove` in`storefront/src/scss/layout/_offcanvas-cart.scss` to keep it at the top right position
* Added class `.offcanvas-cart .cart-item-details-container .cart-item-details ` in `storefront/src/scss/layout/_offcanvas-cart.scss` to prevent the product title overlapped with the remove button
* Added `min-height: 50px` to `.cart-quantity-price` in `storefront/src/scss/layout/_offcanvas-cart.scss` so the promotion item has some padding with the remove button
  