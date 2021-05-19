---
title: Implement showing product properties listing
issue: NEXT-15102
flag: FEATURE_NEXT_12437
---
# Administration
* Added `sw-product-properties` component in `product` module to replace `sw-product-detail-properties` component.
* Deprecated `sw-product-detail-properties` component in `product` module, use `sw-product-properties` instead.
* Added `sw_product_detail_specifications_property` block in `sw-product-detail-specifications` component template to use new `sw-product-properties` component.
