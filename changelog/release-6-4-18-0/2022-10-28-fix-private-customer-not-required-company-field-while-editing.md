---
title: Fix private customer not required company field while editing
issue: NEXT-23059
---
# Administration
* Changed computed `isBusinessAccountType` in `src/module/sw-customer/component/sw-customer-address-form/index.js` to check for existing customer company value.
* Added computed `isValidCompanyField` in `src/module/sw-order/component/sw-order-create-address-modal/index.js` to check required `company` field.
* Changed method `saveCurrentAddress` in `src/module/sw-order/component/sw-order-create-address-modal/index.js` to show an error invalid below `company` field.
