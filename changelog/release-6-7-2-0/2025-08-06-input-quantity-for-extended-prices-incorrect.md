---
title: Input quantity for extended prices incorrect
issue: #11769
author: Le Nguyen
author_email: nguyenquocdaile@gmail.com
author_github: @nguyenquocdaile
---
# Administration
* Changed `onQuantityEndChange` method to do not allow input quantity end is less than quantity start in `src/module/sw-product/view/sw-product-detail-context-prices/index.js`.
* Changed `sw_product_detail_prices_price_card_price_group_grid_quantity_end_field` block to remove min attribute for number field component in `src/module/sw-product/view/sw-product-detail-context-prices/sw-product-detail-context-prices.html.twig`.
