---
title: Fix Null Value Issue for 'zipcode' Column in Database
issue: NEXT-29921
---
# Core
* Changed `Shopware\Core\Checkout\Customer\Validation\AddressValidationFactory` to remove the `zipcode` definition in validation.
* Added migration `Shopware\Core\Migration\V6_5\Migration1695776504UpdateZipCodeOfTableOrderAddressToNullable` to update the `zipcode` column of the `order_address` table to `nullable`.
* Added migration `Shopware\Core\Migration\V6_5\Migration1695778183UpdateStreetOfTableCustomerAddressToNotNull` to update the `street` column of the `customer_address` table to `not null`.
___
# Storefront
* Changed `Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoader` to set the `zipcode` definition in validation.
