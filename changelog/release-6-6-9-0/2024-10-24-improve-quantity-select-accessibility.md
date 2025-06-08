---
title: Improved semantics of product quantity inputs 
issue: NEXT-38926
---
# Storefront
* Added new snippet `component.product.quantitySelect.legend` with more detailed instructions for the quantity selection.
* Changed the surrounding container of the quantity button group from `<div>` to `<fieldset>` in the following files:
  * `src/Storefront/Resources/views/storefront/page/product-detail/buy-widget-form.html.twig`
  * `src/Storefront/Resources/views/storefront/component/buy-widget/buy-widget-form.html.twig`
* Changed the button group within `src/Storefront/Resources/views/storefront/component/line-item/element/quantity.html.twig` to be wrapped by a `<fieldset>` within the block `component_line_item_quantity_select` while wrapping around the block `component_line_item_quantity_select_input`.
* Changed the `<label>` element for the whole button group of the quantity select to a `<legend>` of the new fieldset in the corresponding files.
