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
* Changed in `src/module/sw-customer/component/sw-customer-card/sw-customer-card.html.twig`
    * Changed block `sw_customer_card_metadata_customer_name_editor_company` to make company field always display
    * Changed block `sw_customer_card_password` to disabled password field when customer is guest
    * Changed block `sw_customer_card_password_confirm` to disabled confirm password field when customer is guest and show validation error.
* Changed in `src/module/sw-customer/page/sw-customer-create/index.js`
    * Deprecated `errorEmailCustomer` due to unused
    * Added watcher `customer.accountType`
    * Added `mapPropertyErrors` to show validation error for required fields.
    * Changed method `validateEmail` to show error validation in error summary
    * Changed method `onSave` to remove redundant error notification
* Changed in `src/module/sw-customer/page/sw-customer-detail/index.js`
    * Changed method `validateEmail` to show error validation in error summary
    * Changed method `onSave` to remove redundant error notification
    * Changed method `createErrorMessageForCompanyField` to show error validation in error summary
    * Changed method `validPassword` to show password do not match validation.
* Changed in `src/module/sw-order/component/sw-order-new-customer-modal/index.js`
    * Added computed variable `validCompanyField`
    * Added watcher `customer.salesChannelId`
    * Added watcher `customer.accountType`
    * Changed method `createdComponent` to set customer account type as private initially
    * Changed method `onSave` show error notification correctly
    * Added method `createErrorMessageForCompanyField` to add error validation for company field
    * Added method `validateEmail` to validate if email already used.
