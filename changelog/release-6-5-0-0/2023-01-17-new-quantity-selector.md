---
title: New Quantity Selector
issue: NEXT-23646
---
# Storefront
* Changed the quantity selector in `Storefront/Resources/views/storefront/component/buy-widget/buy-widget-form.html.twig`, `Storefront/Resources/views/storefront/component/buy-widget/buy-widget-form.html.twig` and `Storefront/Resources/views/storefront/component/line-item/element/quantity.html.twig` from a select element to a new input element with + and - buttons.
* Removed block `page_product_detail_buy_quantity_select` in `Storefront/Resources/views/storefront/component/buy-widget/buy-widget-form.html.twig` and `Storefront/Resources/views/storefront/component/buy-widget/buy-widget-form.html.twig`.
* Removed block `component_line_item_quantity_select_select` in `Storefront/Resources/views/storefront/component/line-item/element/quantity.html.twig`.
* Added new js plugin `QuantitySelectorPlugin` in `quantity-selector.plugin.js`
* Changed `FormAutoSubmitPlugin` in `form-auto-submit.plugin.js` to respect a `delayChangeEvent` option to delay the submit request for the given amount of milliseconds.
* Changed `OffCanvasCartPlugin.options.changeQuantityInputDelay` to 800 milliseconds.
