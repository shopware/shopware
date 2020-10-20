---
title: Customer addresses showing wrong on Editing order
issue: NEXT-10971
---
# Administration
* Changed method `createdComponent()` in `module/sw-order/component/sw-order-address-modal/index.js` to get the customer information when needed.
* Added method `getCustomerInfo()` in `module/sw-order/component/sw-order-address-modal/index.js` to perform getting the customer information.
