---
title: Fix discounts in Variants
issue: NEXT-10802
author: Simon Vorgers
author_email: s.vorgers@shopware.com 
---
# Administration
* Changed `sw-products-variants-generator` to correctly support negative numbers
* Changed `sw-number-field` to correctly support negative numbers
* Deprecated function `applyDigits` in `sw-number-field`
* Deprecated property `onlyPositive` in `sw-product-variants-price-field`
* Changed german/english translation for snippet key `sw-product.configuratorModal.labelCreateNew`
* Changed german/english translation for snippet key `sw-product.configuratorModal.priceSurcharges`
* Changed german/english translation for snippet key `sw-product.configuratorModal.resetSurcharges`
* Changed german/english translation for snippet key `sw-product.configuratorModal.surchargeNotice`
* Added e2e-test `src/Administration/Resources/app/administration/test/e2e/cypress/integration/catalogue/sw-product/edit-variant-prices.spec.js`
* Added jest test `src/Administration/Resources/app/administration/test/app/helper/sw-products-variants-generator.spec.js`
