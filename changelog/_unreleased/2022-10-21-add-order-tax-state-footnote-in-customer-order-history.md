---
title: Add order tax state footnote in customer's order history
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Storefront
* Added twig variable `isTaxStateSame` in block `page_account_order_item_detail_overview` that indicates whether the order's tax status is the same of the context's tax state 
* Added new block `page_account_order_item_detail_table_footnote` after `page_account_order_item_detail_table_labels_summary` to display a footnote when the order has a different tax state indicated by `isTaxStateSame`
* Extracted usage of `general.star` into `taxFootNote` variable in the blocks `component_line_item_total_price`, `component_line_item_unit_price` and `page_account_order_item_detail_list_item` to easily exchange it with the double `general.star` to build a footnote, that is not related to the global tax state hint in the footer
