---
title: fixed country region validation in checkout
issue: NEXT-30785
author: Florian Keller
author_email: f.keller@shopware.com
---
# Core
* Changed `Shopware\Core\Checkout\Cart\Address::validate()` to check if country region is set, when the value is required.
* Changed `src/Storefront/Resources/snippet/en_GB/storefront.en-GB.json` and `src/Storefront/Resources/snippet/de_DE/storefront.de-DE.json` and added error message.
* Added `Shopware\Core\Checkout\Cart\Address\Error::CountryRegionMissingError`, `Shopware\Core\Checkout\Cart\Address\Error::BillingAddressCountryRegionMissingError` and `Shopware\Core\Checkout\Cart\Address\Error::ShippingAddressCountryRegionMissingError` to create error scheme. 

