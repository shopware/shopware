---
title: Fix variant listing config when cloning products or deleting variants
issue: NEXT-37442
author: Felix Schneider
author_email: felix@wirduzen.de
author_github: schneider-felix
---
# Administration
* Changed `src/Administration/Resources/app/administration/src/module/sw-product/component/sw-product-clone-modal`
to prevent changing the variant listing config of the original product on duplication
* Changed `src/Administration/Resources/app/administration/src/module/sw-product/component/sw-product-variants/sw-product-variants-overview/index.js`
to reset mainVariantId when the corresponding variant is deleted
