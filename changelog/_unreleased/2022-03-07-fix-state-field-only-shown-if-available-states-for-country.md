---
title: Fix state field only shown if available states for country
issue: NEXT-19473
---
# Administration
* Added in `src/module/sw-customer/component/sw-customer-address-form/index.js`
  * Added computed `hasStates` to check if states are available.
  * Added computed `countryStateRepository`
  * Added method `getCountryStates` to get country state respectively to countryId.
