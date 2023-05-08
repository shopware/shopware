---
title: Moved closing div in correct twig block
issue: NEXT-26331
author: Ioannis Pourliotis
author_email: dev@pourliotis.de
author_github: @PheysX
---
# Storefront
* Changed block `component_line_item_quantity_select_input` in `Resources/views/storefront/component/line-item/element/quantity.html.twig` and moved closing block tag to surround closing `</div>` of element `.line-item-quantity-group`
