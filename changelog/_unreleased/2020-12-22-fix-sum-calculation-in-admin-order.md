---
title: Fix sum calculation in admin order
issue: NEXT-12408
---
# Administration
* Changed `{% block sw_order_detail_base_line_items_summary_amount_total %}` in `src/module/sw-order/view/sw-order-detail-base/sw-order-detail-base.html.twig` to show total price even if tax status is tax free 
* Changed `{% block sw_order_create_base_line_items_summary_amount_total %}` in `src/module/sw-order/view/sw-order-detail-base/sw-order-create-base.html.twig` to show total price even if tax status is tax free
* Deprecated `{% block sw_order_detail_base_line_items_summary_amount_free_tax %}` in `src/module/sw-order/view/sw-order-detail-base/sw-order-detail-base.html.twig`
* Deprecated `{% block sw_order_create_base_line_items_summary_amount_free_tax %}` in `src/module/sw-order/view/sw-order-detail-base/sw-order-create-base.html.twig`
* Changed `sw-product-variant-info` style in `src/module/sw-order/component/sw-order-product-select/sw-order-product-select.scss` to make product name align center
* Changed `label` title of `totalPrice` column in `src/module/sw-order/component/sw-order-line-items-grid/index.js` to show correct title based on tax status
* Changed `label` title of `totalPrice` column in `src/module/sw-order/component/sw-order-line-items-grid-sales-channel/index.js` to show correct title based on tax status
