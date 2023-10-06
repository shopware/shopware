---
title: account-type is missing in administration
issue: NEXT-13585
---
# Administration
* Added block `sw_customer_base_form_account_type_field` at `module/sw-customer/component/sw-customer-base-form/sw-customer-base-form.html.twig` to add account type select field
* Added block `sw_customer_card_account_type_field` at `module/sw-customer/component/sw-customer-card/sw-customer-card.html.twig`  to add account type select field
* Changed `department_field`, `vat_ids` at `module/sw-customer/component/sw-customer-card/sw-customer-card.html.twig`
* Changed class `.sw-customer-card__salutation-select` at `module/sw-customer/component/sw-customer-card/sw-customer-card.scss`
* Added file `module/sw-customer/constant/sw-customer.constant.js` to add module constants for customer's type
* Added computed properties `accountTypeOptions`, `isBusinessAccountType` at `module/sw-customer/component/sw-customer-base-form/index.js`
* Added computed properties `accountTypeOptions`, `isBusinessAccountType` at `module/sw-customer/component/sw-customer-card/index.js`
* Added computed property `validCompanyField`, method `createErrorMessageForCompanyField()` at `module/sw-customer/page/sw-customer-create/index.js` to validate company field in business customer's type
* Changed method `onSave` at `module/sw-customer/page/sw-customer-create/index.js` to handle validate company field in business customer's type
* Added computed property `validCompanyField`, method `createErrorMessageForCompanyField()` at `module/sw-customer/page/sw-customer-detail/index.js` to validate company field in business customer's type
* Changed method `onSave` at `module/sw-customer/page/sw-customer-detail/index.js` to handle validate company field in business customer's type
* Added new translation `sw-customer.error.COMPANY_IS_REQUIRED` at `module/sw-customer/snippet/de-DE.json` & `module/sw-customer/snippet/en-GB.json`
* Added new translations `sw-customer.customerType.labelAccountType`, `sw-customer.customerType.placeholderAccountType`, `sw-customer.customerType.labelPrivate`, `sw-customer.customerType.labelBusiness` at `module/sw-customer/snippet/de-DE.json` & `module/sw-customer/snippet/en-GB.json`
