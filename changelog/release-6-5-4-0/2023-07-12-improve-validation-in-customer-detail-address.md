---
title: Improve validation in customer detail address
issue: NEXT-25247
---
# Administration
* Changed `isValidAddress` method in `sw-customer-detail-addresses` component to dispatch `error/addApiError`.
* Added watchers `country.forceStateInRegistration` and `country.postalCodeRequired` in `sw-customer-address-form` component to update the required flag of property.
* Changed `sw-text-field` component with field name `zipcode` in `src/module/sw-customer/component/sw-customer-address-form/sw-customer-address-form.html.twig` to update required attribute by `country.postalCodeRequired`.
