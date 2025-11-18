---
title: Fix order creation page reload during confirmation dialog when clicking save order button
issue: #12252
---
# Administration
* Changed `onSaveOrder` method in `module/sw-order/page/sw-order-create/index.ts` to prevent immediate navigation when confirmation dialog is shown by removing `this.isSaveSuccessful = true` from the save completion flow
