---
title: Improve errors in order new customer modal
issue: NEXT-25342
---
# Administration
* Added `mapPageErrors` in `sw-order-new-customer-modal` component to get api errors.
* Added property `has-error` in `src/module/sw-order/component/sw-order-new-customer-modal/sw-order-new-customer-modal.html.twig` to show error on `sw-tabs-item` component.
* Changed property `error` of `sw-text-field` component in `src/module/sw-customer/component/sw-customer-address-form/sw-customer-address-form.html.twig` to hide error when has disabled `sw-text-field`.
