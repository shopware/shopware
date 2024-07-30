---
title: The form element quantity selector is not labeled
issue: NEXT-33695
---
# Storefront
* Added the hidden `label` element inside the quantitty group element in `Resources/views/storefront/component/line-item/element/quantity.html.twig` and `Resources/views/storefront/page/product-detail/buy-widget-form.html.twig`
* Added an attribute `aria-hidden="true"` for the label of line-item in `Resources/views/storefront/component/line-item/element/quantity.html.twig`
