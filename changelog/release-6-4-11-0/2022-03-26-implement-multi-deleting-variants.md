---
title: Implement multi deleting variants
issue: NEXT-20550
---
# Administration
* Added data variables `toBeDeletedVariantsId` in `sw-product-variants-overview` component
* Added method `onClickBulkDelete` in `sw-product-variants-overview` component
* Changed computed of `canBeDeletedCriteria` and the following methods in `sw-product-variants-overview` component:
    * `onVariationDelete`
    * `onCloseDeleteModal`
    * `onConfirmDelete`
* Added block `sw_product_variants_overview_bulk` in `sw-product-variants-overview` component template
