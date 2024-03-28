---
title: Cleanup product/card/action.html.twig template
issue: NEXT-34415
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Storefront
* Deprecated unused function `ViewItemListEvent.fetchProductId` in `app/storefront/src/plugin/google-analytics/events/view-item-list.event.js`
* Changed `ViewItemListEvent` to use the `data-product-information` attribute of the product box for the product infomation for Shopware version 6.7.0.0
* Deprecated block `component_product_box_action_meta` from `storefront/component/product/card/action.html.twig`
* Deprecated blocks `page_product_detail_buy_product_buy_info`, `page_product_detail_product_buy_meta` and `page_product_detail_product_buy_button` from `storefront/component/product/card/action.html.twig` and added replacement blocks `component_product_box_action_buy_info`, `component_product_box_action_buy_meta` and `component_product_box_action_buy_button`
* Added blocks `page_product_detail_product_buy_button_label` and `page_product_detail_product_buy_button_label` to `storefront/component/product/card/action.html.twig` template
