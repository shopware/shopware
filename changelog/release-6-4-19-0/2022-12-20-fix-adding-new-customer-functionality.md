---
title: Fix adding new customer functionality
issue: NEXT-24382
---
# Administration
* Changed computed variable `isBusinessAccountType` in `src/module/sw-customer/component/sw-customer-address-form/index.js`
* Changed block `sw_customer_address_form_company_field` and `sw_customer_address_form_department_field` in `src/module/sw-customer/component/sw-customer-address-form/sw-customer-address-form.html.twig` to make it always show company and department fields
* Added `mapPropertyErrors` in `src/module/sw-customer/component/sw-customer-base-info/index.js` to show validation error for required fields.
* Changed in `src/module/sw-customer/component/sw-customer-base-info/sw-customer-base-info.html.twig` to add error validation for customer group selection and payment method selection.
* Changed in `src/module/sw-customer/component/sw-customer-card/index.js`
  * Added `mapPropertyErrors` to show validation error for required fields.
  * Added watcher `customer.accountType`
