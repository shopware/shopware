---
title: Improve modal accessibility in the Storefront
issue: NEXT-39211
---
# Storefront
* Added `role="dialog"`, `aria-modal`, and `aria-labelledby` attributes to all modals in:
  * `page/account/order-history/cancel-order-modal.html.twig`
  * `page/account/order/cancel-order-modal.html.twig`
  * `page/account/profile/index.html.twig`
  * `utilities/qr-code-modal.html.twig`
  * `component/pseudo-modal.html.twig`
* Changed `component/address/address-editor-modal.html.twig` to use the corresponding headline for the modal title.
* Added a general fix for the bootstrap modal to clear focus before applying the `aria-hidden="true"` attribute to prevent an accessibility console error by the browser.
* Deprecated `page/account/order-history/cancel-order-modal.html.twig` for the v6.7 version. Use `page/account/order/cancel-order-modal.html.twig` instead.
