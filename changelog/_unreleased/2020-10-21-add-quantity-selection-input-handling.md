---
title:              Add option to display input instead of select field for quantities
issue:              NEXT-11540
author:             Sebastian König
author_email:       s.koenig@tinect.de
author_github:      @tinect
---
# Storefront
* Added new utility template `storefront/utilities/quantity-selection.html.twig` to take care of input or select output
* Changed block `component_offcanvas_product_buy_quantity` in `component/checkout/offcanvas-item.html.twig` to use new utility
* Changed block `page_product_detail_buy_quantity` in `page/checkout/checkout-item.html.twig` to use new utility
* Changed block `page_product_detail_buy_quantity` in `page/product-detail/buy-widget-form.html.twig` to use new utility
___
# Administration
* Added config `cart.maxSelectFieldOptions` in cart settings to specifiy when input field should be displayed instead of the select field
___
