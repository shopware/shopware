---
title: Add product search bar to edit orders
issue: NEXT-10694
---
# Administration
* Added product search bar `{% block sw_order_line_items_grid_line_item_filter %}` to `module/sw-order/component/sw-order-line-items-grid` for product search in Edit order page
* Changed computed method `orderLineItems` in `module/sw-order/component/sw-order-line-items-grid` to filter product by label in Edit order page
