---
title: Allow bulking update for Customer Group Registration Api
issue: NEXT-17874
---
# Core
* Changed `\Shopware\Core\Checkout\Customer\Api\CustomerGroupRegistrationActionController` to accept `customerIds` and `silentError` as body parameter to allow bulking accept or decline API
* Deprecated `customerId` route parameter in `api.customer-group.accept` and `api.customer-group.decline` api, use `customerIds` in body instead
___
# Administration
* Changed `customer-group-registration.api.service.js` accept and decline methods to send customerId in `customerIds` body payload instead of as a route parameter
