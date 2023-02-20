---
title: Migrate account type for customer
issue: NEXT-25126
---
# Core
* Added migration `Migration1676272000AddAccountTypeToCustomer` to add new column `account_type` with default value is `private` into table `customer`, and update value of `account_type` for all current customers.
* Added migration `Migration1676272001AddAccountTypeToCustomerProfileImportExport` to add new mapping key `account_type` to customer profile
* Changed `Shopware\Core\Checkout\Customer\Validation\CustomerProfileValidationFactory` to add validation for `account_type` 
* Added `accountType` string field to `Shopware\Core\Checkout\Customer\CustomerDefinition`.
* Added `get` and `set` methods for `$accountType` to `Shopware\Core\Checkout\Customer\CustomerEntity`.
* Added new `collection` with `key="customer.account_types"` to `Core/Checkout/DependencyInjection/customer.xml`
___
# API
* Changed logic of route `store-api.account.change-profile` to get `account_type` from request databag 
* Changed logic of route `store-api.account.register` to get `account_type` from request databag 
___
# Administration
* Changed function `createdComponent` at `/src/module/sw-customer/page/sw-customer-detail/index.js` to not automatically assign value to `customer.accountType` based on `customer.company`
