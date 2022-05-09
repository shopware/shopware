---
title: Apply address formatting for several places
issue: NEXT-21001
---
# Core
* Added `Shopware\Core\System\Country\Service\CountryAddressFormattingService` into `__constructor` of the following files:
  * `Shopware\Core\Checkout\Document\DocumentGenerator\DeliveryNoteGenerator`
  * `Shopware\Core\Checkout\Document\DocumentGenerator\CreditNoteGenerator`
  * `Shopware\Core\Checkout\Document\DocumentGenerator\InvoiceGenerator`
  * `Shopware\Core\Checkout\Document\DocumentGenerator\StornoGenerator`
* Changed method `generate` to added new parameter `formattingAddress` before render document of the following files:
  * `Shopware\Core\Checkout\Document\DocumentGenerator\DeliveryNoteGenerator`
  * `Shopware\Core\Checkout\Document\DocumentGenerator\CreditNoteGenerator`
  * `Shopware\Core\Checkout\Document\DocumentGenerator\InvoiceGenerator`
  * `Shopware\Core\Checkout\Document\DocumentGenerator\StornoGenerator`
* Changed template to render `formattingAddress` if it is defined in the following files:
  * `@Framework/documents/includes/letter_header.html.twig`
  * `@Framework/documents/delivery_note.html.twig`
* Added new controller `Shopware\Core\System\Country\Api\CountryActionController`
* Added new service `Shopware\Core\System\Country\Service\CountryAddressFormattingService`
* Added new struct `Shopware\Core\System\Country\Struct\CountryAddress`
___
# API
* Added new api `/_action/country/formatting-address` to render new formatting address
___
# Administration
* Added new props `formattingAddress` into `/src/app/component/base/sw-address/index.ts`
* Changed template `/src/app/component/base/sw-address/sw-address.html.twig` to render `formattingAddress` if it was defined
* Added new api service `src/core/service/api/country-address.api.service.js` as `countryAddressService`
* Added `countryAddressService` and method `renderFormattingAddress` at the following files:
  * `/src/module/sw-customer/component/sw-customer-default-addresses/index.js`
  * `/src/module/sw-order/component/sw-order-delivery-metadata/index.js`
  * `/src/module/sw-order/component/sw-order-user-card/index.js`
* Added new data `formattingShippingAddress` and `formattingBillingAddress` at `/src/module/sw-customer/component/sw-customer-default-addresses/index.js`
* Added new data `formattingAddress` at the following files:
  * `/src/module/sw-order/component/sw-order-delivery-metadata/index.js`
  * `/src/module/sw-order/component/sw-order-user-card/index.js`
* Changed template to render `formattingAddress` at the following files:
  * `/src/module/sw-customer/component/sw-customer-default-addresses/sw-customer-default-addresses.html.twig`
  * `/src/module/sw-order/component/sw-order-delivery-metadata/sw-order-delivery-metadata.html.twig`
  * `/src/module/sw-order/component/sw-order-user-card/sw-order-user-card.html.twig`
___
# Storefront
* Added `Shopware\Core\System\Country\Service\CountryAddressFormattingService` into `__constructor` of the following files:
    * `Shopware\Storefront\Page\Address\Listing\AddressListingPageLoader`
* Added new property `formattingCustomerAddresses` at `Shopware\Storefront\Page\Address\Listing\AddressListingPage`
* Changed method `load` to render new formatting address if needed at `Shopware\Storefront\Page\Address\Listing\AddressListingPageLoader`
* Changed template `src/Storefront/Resources/views/storefront/component/address/address.html.twig` to render `page.formattingCustomerAddresses`
