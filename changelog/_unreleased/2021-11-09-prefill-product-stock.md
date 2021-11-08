---
title: Prefill product stock
issue: NEXT-18180
flag: FEATURE_NEXT_17546
author_github: @Dominik28111
---
# Administration
* Changed block `sw_product_deliverability_form_stock_field` in `component/sw-product-deliverability-form/sw-product-deliverability-form.html.twig` to no longer require a value for the field. 
* Added lifecycle hook `created` in `component/sw-product-deliverability-form/sw-product-deliverability-form/index.js`. 
* Added method `createdComponent` in `component/sw-product-deliverability-form/sw-product-deliverability-form/index.js` to prefill value `product.stock` with `0`.
