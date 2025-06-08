---
title: Fix storefront presentation error
issue: NEXT-23378
author: Ramona Schwering
author_email: r.schwering@shopware.com
author_github: @leichteckig
---
# Administration
* Changed `sw-product-modal-delivery/index.js` to consider additional edge cases in variant data
* Changed the following components to support new `variantListingConfig`
  * `sw-product-variants-delivery-media`
  * `sw-product-variants-delivery-order`
* Changed `composer.json` for Cypress 10 support
