---
title: Improve password error handling for customer
issue: NEXT-23044
---
# Administration
* Added computed `validPasswordField` in `src/module/sw-customer/page/sw-customer-create/index` to check the length of `password`.
* Added `getDefaultRegistrationConfig` method in `src/module/sw-customer/page/sw-customer-create/index` to load registration config.
* Changed `onSave` in `src/module/sw-customer/page/sw-customer-create/index` to check password validation when saving customer data.
