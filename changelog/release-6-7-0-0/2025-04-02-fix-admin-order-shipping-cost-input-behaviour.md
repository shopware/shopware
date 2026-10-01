---
title: Fix admin order shipping cost input behaviour
issue: https://github.com/shopware/shopware/issues/7958
author_github: @En0Ma1259
---
___
# Administration
* Removed `fill-digits` option on `shippingCosts` field
    * `src/Administration/Resources/app/administration/src/module/sw-order/view/sw-order-detail-details/sw-order-detail-details.html.twig`
    * `src/Administration/Resources/app/administration/src/module/sw-order/view/sw-order-create-details/sw-order-create-details.html.twig`
* Added `step` option on `shippingCosts` field
  * `src/Administration/Resources/app/administration/src/module/sw-order/component/sw-order-create-options/sw-order-create-options.html.twig`
  * `src/Administration/Resources/app/administration/src/module/sw-order/view/sw-order-detail-details/sw-order-detail-details.html.twig`
  * `src/Administration/Resources/app/administration/src/module/sw-order/view/sw-order-create-details/sw-order-create-details.html.twig`
  * `src/Administration/Resources/app/administration/src/module/sw-order/view/sw-order-detail-general/sw-order-detail-general.html.twig`
* Added `debounce` to `onShippingChargeEdited` method in `src/Administration/Resources/app/administration/src/module/sw-order/view/sw-order-detail-details/index.js`
* Changed styles for `src/Administration/Resources/app/administration/src/module/sw-order/component/sw-order-saveable-field/sw-order-saveable-field.html.twig`
