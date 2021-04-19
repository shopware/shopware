---
title: Fix cross selling preview does not show any products
issue: NEXT-14292
---
# Administration
* Changed `openModalPreview` method in `sw-product-cross-selling-form` component to make sure modal will be opened after finishing to get product stream filters.
* Changed `loadStreamPreview` method in `sw-product-cross-selling-form` component to get product stream filters if needed.
* Changed `sw_product_detail_cross_selling_modal_preview_modal` block in `sw-product-cross-selling-form` component template.
* Added `sw_product_cross_selling_form_condition_tree_invisibly` block in `sw-product-cross-selling-form` component template.
