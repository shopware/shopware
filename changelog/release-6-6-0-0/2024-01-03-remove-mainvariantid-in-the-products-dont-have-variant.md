---
title: Reset `variantListingConfig.mainVariantId` when clone the product
issue: NEXT-26217
author: kyle
---
# Administration
* Changed the method `cloneParent` in `platform/src/Administration/Resources/app/administration/src/module/sw-product/component/sw-product-clone-modal/index.js` to reset the `variantListingConfig.mainVariantId` when clone the product.
