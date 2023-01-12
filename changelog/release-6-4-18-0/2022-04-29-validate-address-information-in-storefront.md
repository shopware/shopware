---
title: Validate address information in storefront
issue: NEXT-21003
---
# Core
* Changed method `defineFields` of `Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition` to update field `zipcode` is not required
* Changed property `zipcode` and method `getZipcode`, `getZipcode` of `Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity` to update field `zipcode` nullable
* Changed method `getCreateAddressValidationDefinition` of `Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute` to add validation rule for `zipcode`
* Changed method `getValidationDefinition` of `Shopware\Core\Checkout\Customer\SalesChannel\UpsertAddressRoute` to add validation rule for `zipcode`
* Added Constraint class `Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerZipCode` for creating zipcode constraint
* Added Validator class `Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerZipCodeValidator` for handling zipcode validation
* Added migration `Migration1651118773UpdateZipCodeOfTableCustomerAddressToNullable` to update zipcode field in database into nullable
___
# Storefront
* Added translation for `VIOLATION::ZIP_CODE_INVALID` of `Resources/snippet/de_DE/storefront.de-DE.json` & `Resources/snippet/de_DE/storefront.en-GB.json`
* Changed block `component_address_form_zipcode_city` of `Resources/views/storefront/component/address/address-form.html.twig` to update field `zipcode` is not required
