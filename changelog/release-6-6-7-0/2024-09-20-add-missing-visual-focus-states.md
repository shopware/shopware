---
title: Add missing visual focus states
issue: NEXT-26712
---
# Storefront
* Changed `filter-panel-offcanvas-close` from `<div>` to `<button>` with Bootstrap class `btn-close` in `Resources/views/storefront/component/listing/filter-panel.html.twig`.
* Changed wishlist button selectors `product-wishlist-action-circle` and `product-wishlist-btn-remove` to unify their styling and apply a visible focus state. The base styling is applied by the class `product-wishlist-btn`.