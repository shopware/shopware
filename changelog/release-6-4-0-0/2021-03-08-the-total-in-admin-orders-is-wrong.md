---
title: The total in admin orders is wrong
issue: NEXT-13708
---
# Administration
* Change filter `currency` of block `sw_order_detail_base_line_items_summary_amount` in `src/module/sw-order/view/sw-order-detail-base/sw-order-detail-base.html.twig` to format decimals total rounding.
* Change filter `currency` of block `sw_order_detail_base_line_items_summary_amount_without_tax` in `src/module/sw-order/view/sw-order-detail-base/sw-order-detail-base.html.twig` to format decimals total rounding.
