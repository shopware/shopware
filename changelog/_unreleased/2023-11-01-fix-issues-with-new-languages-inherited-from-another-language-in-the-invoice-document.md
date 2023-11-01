---
title: Fix issues with new languages inherited from another language in the invoice document
issue: NEXT-31145
---
# Core
* Changed `src/Core/Framework/Resources/views/documents/includes/payment_shipping.html.twig` to use translated with name field.
* Changed block `document_line_item_table_shipping_label` in `src/Core/Framework/Resources/views/documents/includes/shipping_costs.html.twig` to use translated with name field.
