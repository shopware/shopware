---
title: Fix shipping costs can not changes after order is submitted
issue: NEXT-36415
---
# Administration
* Added `onShippingChargeUpdated` method in `src/module/sw-order/view/sw-order-detail-general/index.js` to update shipping costs.
* Added `onShippingChargeUpdated` method in `src/module/sw-order/view/sw-order-create-base/index.js` to update shipping costs.
* Added `onShippingChargeUpdated` method in `src/module/sw-order/view/sw-order-create-general/index.ts` to update shipping costs.
* Changed `src/module/sw-order/component/sw-order-saveable-field/sw-order-saveable-field.html.twig` to add $listeners for a dynamic component. This change likely allows the component to respond to events or changes in state.
