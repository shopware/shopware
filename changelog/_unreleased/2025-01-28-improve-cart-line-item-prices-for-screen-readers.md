---
title: Improve cart line item prices for screen readers
issue: NEXT-33687
---
# Storefront
* Changed CSS of the following line item labels to use `visually-hidden` instead of `display: none` so they can be read by screen readers:
    * `line-item-unit-price-label`
    * `line-item-tax-price-label`
    * `line-item-total-price-label`
* Changed `Resources/views/storefront/component/line-item/element/label.html.twig` to render an additional hidden label to announce the product metadata.
* Changed the following cart templates to also display the current amount of line-items:
    * `src/Storefront/Resources/views/storefront/page/checkout/cart/index.html.twig`
    * `src/Storefront/Resources/views/storefront/page/checkout/confirm/index.html.twig`