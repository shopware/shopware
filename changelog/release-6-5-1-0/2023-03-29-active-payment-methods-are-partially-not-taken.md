---
title: Active payment methods are partially not taken into account in admin orders
issue: NEXT-25515
---
# Administration
* Removed help text of payment method field in the files:
  - `src/module/sw-order/view/sw-order-create-details/sw-order-create-details.html.twig`
  - `src/module/sw-order/view/sw-order-detail-details/sw-order-detail-details.html.twig`
  - `src/module/sw-order/component/sw-order-create-options/sw-order-create-options.html.twig`
* Changed computed `paymentMethodCriteria` to remove filter `afterOrderEnabled` in files:
  - `src/module/sw-order/view/sw-order-create-details/index.ts`
  - `src/module/sw-order/view/sw-order-detail-details/index.js`
  - `src/module/sw-order/component/sw-order-create-options/sw-order-create-options.spec.ts`
