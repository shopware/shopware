---
title: Credit note total amounts reflect sums of credit line items
issue: NEXT-15364
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Changed summary amounts in credit note to only reflect sums of credit line items
* Changed summary amounts and credit line item position values to be positive rather than negative
* Changed `credit_note.html.twig` to include empty `document_loop_shipping_costs` block to not show shipping costs
