---
title: Optional salutation
issue: NEXT-7739
flag: FEATURE_NEXT_7739
---
# Core
* Added new Error classes:
    - `src/Core/Checkout/Cart/Address/Error/SalutationMissingError.php`
    - `src/Core/Checkout/Cart/Address/Error/ProfileSalutationMissingError.php`
    - `src/Core/Checkout/Cart/Address/Error/BillingAddressSalutationMissingError.php`
    - `src/Core/Checkout/Cart/Address/Error/ShippingAddressSalutationMissingError.php`
* Changed `src/Core/Checkout/Cart/Address/AddressValidator.php` to add cart errors when no salutation is present on the customer profile, or any of the chosen addresses
* Changed definitions and entities to allow for an empty `salutationId`:
    - `src/Core/Checkout/Customer/Aggregate/CustomerAddress/CustomerAddressDefinition.php`
    - `src/Core/Checkout/Customer/Aggregate/CustomerAddress/CustomerAddressEntity.php`
    - `src/Core/Checkout/Customer/CustomerDefinition.php`
    - `src/Core/Checkout/Customer/CustomerEntity.php`
    - `src/Core/Checkout/Order/Aggregate/OrderAddress/OrderAddressEntity.php`
    - `src/Core/Checkout/Order/Aggregate/OrderCustomer/OrderCustomerEntity.php`
    - `src/Core/System/Salutation/SalutationEntity.php`
* Changed `src/Core/Checkout/Customer/Validation/AddressValidationFactory.php` to allow empty `salutationId`'s
* Changed `src/Core/Checkout/Customer/Validation/CustomerProfileValidationFactory.php` to allow empty `salutationId`'s
* Added `src/Core/Migration/V6_4/Migration1623305620ChangeSalutationIdNullable.php`
* Added `getRoute` method to `src/Core/Checkout/Cart/Error/Error.php`
* Added `src/Core/Checkout/Cart/Error/ErrorRoute.php`
* Added `src/Core/Checkout/Customer/Subscriber/CustomerDefaultSalutationSubscriber.php`
* Added `FEATURE_NEXT_7739` flag
* Changed the following templates to filter out the default salutation and allow for an empty one
  - `src/Core/Framework/Resources/views/documents/includes/letter_header.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_confirmation_mail/de-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_delivery.state.cancelled/de-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_delivery.state.returned/de-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_delivery.state.returned_partially/de-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_delivery.state.shipped/de-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_delivery.state.shipped_partially/de-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order.state.cancelled/de-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order.state.completed/de-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order.state.in_progress/de-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order.state.open/de-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.cancelled/de-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.open/de-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.paid/de-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.paid_partially/de-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.refunded/de-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.refunded_partially/de-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.reminded/de-html.html.twig`- `src/Core/Migration/Fixtures/mails/order_confirmation_mail/de-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_delivery.state.cancelled/en-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_delivery.state.returned/en-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_delivery.state.returned_partially/en-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_delivery.state.shipped/en-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_delivery.state.shipped_partially/en-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order.state.cancelled/en-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order.state.completed/en-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order.state.in_progress/en-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order.state.open/en-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.cancelled/en-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.open/en-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.paid/en-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.paid_partially/en-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.refunded/en-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.refunded_partially/en-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.reminded/en-html.html.twig`- `src/Core/Migration/Fixtures/mails/order_delivery.state.cancelled/en-html.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_delivery.state.returned/de-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_delivery.state.returned_partially/de-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_delivery.state.shipped/de-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_delivery.state.shipped_partially/de-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order.state.cancelled/de-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order.state.completed/de-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order.state.in_progress/de-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order.state.open/de-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.cancelled/de-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.open/de-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.paid/de-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.paid_partially/de-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.refunded/de-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.refunded_partially/de-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.reminded/de-plain.html.twig`- `src/Core/Migration/Fixtures/mails/order_delivery.state.returned/de-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_delivery.state.returned_partially/en-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_delivery.state.shipped/en-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_delivery.state.shipped_partially/en-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order.state.cancelled/en-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order.state.completed/en-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order.state.in_progress/en-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order.state.open/en-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.cancelled/en-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.open/en-plain.html.twig`com
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.paid/en-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.paid_partially/en-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.refunded/en-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.refunded_partially/en-plain.html.twig`
  - `src/Core/Migration/Fixtures/mails/order_transaction.state.reminded/en-plain.html.twig`
* Added `src/Core/Migration/V6_4/Migration1624967118updateMailTemplatesWithOptionalSalutation.php`
* Added `src/Core/Migration/V6_4/Migration1625465756DefaultSalutation.php`
* Added `SALUTATION` to `src/Core/Defaults.php`
___
# Administration
* Changed `src/Administration/Resources/app/administration/src/module/sw-customer/component/sw-customer-base-form/sw-customer-base-form.html.twig` so that the salutation field is no longer required
* Changed `src/Administration/Resources/app/administration/src/app/filter/salutation.filter.js` so the default salutation is filtered out
* Added `salutationCriteria` to
  - `src/Administration/Resources/app/administration/src/module/sw-customer/component/sw-customer-address-form/index.js`
  - `src/Administration/Resources/app/administration/src/module/sw-customer/component/sw-customer-base-form/index.js`
  - `src/Administration/Resources/app/administration/src/module/sw-customer/component/sw-customer-card/index.js`
* Changed the following templates, so the default salutation is filtered out:
  - `src/Administration/Resources/app/administration/src/module/sw-customer/component/sw-customer-address-form/sw-customer-address-form.html.twig`
  - `src/Administration/Resources/app/administration/src/module/sw-customer/component/sw-customer-base-form/sw-customer-base-form.html.twig`
  - `src/Administration/Resources/app/administration/src/module/sw-customer/component/sw-customer-card/sw-customer-card.html.twig`
* Added `defaultSalutationId` to `src/Administration/Resources/app/administration/src/core/shopware.js`
___
# Storefront
* Changed `src/Storefront/Controller/RegisterController.php` so a salutation is mandatory when signing up via storefront
* Changed templates to check whether a salutation exists before accessing it:
    - `src/Storefront/Resources/views/storefront/component/address/address.html.twig`
    - `src/Storefront/Resources/views/storefront/page/account/index.html.twig`
* Changed `src/Storefront/Controller/StorefrontController.php` so it checks for `ErrorRoute` objects in `Error`s and completes them with URLs
* Changed `src/Storefront/Resources/views/storefront/component/address/address-personal.html.twig` so the default salutation is filtered out
___
# Upgrade information
The salutation is now optional as far as the Shopware core is concerned. Validations are still in place for orders, so
a salutation needs to be set by customers during checkout in case there's none defined.

## Plugin compatibility tasks
In essence this change means that the following getters may return `null` from now on:
  - `CustomerEntity::getSalutation()`
  - `CustomerEntity::getSalutationId()`
  - `CustomerAddressEntity::getSalutation()`
  - `CustomerAddressEntity::getSalutationId()`
  - `OrderAddressEntity::getSalutation()`
  - `OrderAddressEntity::getSalutationId()`
  - `CustomerEntity::getSalutation()`
  - `CustomerEntity::getSalutationId()`
  - `OrderCustomerEntity::getSalutation()`
  - `OrderCustomerEntity::getSalutationId()`

Please check your plugins for type hints or other references to these. Following this, any logic regarding
salutations should be altered to allow for none being set. This goes for templates, storefront and administration
javascript, as well as backend code.

## Backwards compatibility
A subscriber responsible for providing a salutation stub will be in place until the next major version v6.5.
This subscriber adds the default salutation `ed643807c9f84cc8b50132ea3ccb1c3b` to any customer- or address-related
entity which does not contain one, when it's read from the database.
Plugins will therefore continue to work at least code-wise until they've been adjusted like described in the plugin
compatibility tasks section.
