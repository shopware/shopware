---
title: 2024-03-22-update-register-from-validation
issue: NEXT-34217
author: Florian Keller
author_email: f.keller@shopware.com
---
# Core
* Changed `Checkout/Customer/SalesChannel/RegisterRoute.php` to correctly set first and last name for address and customer profile validation with next major.
* Added length validation for zipcode, title, first- and last name to `Checkout/Customer/Validation/AddressValidationFactory.php` with next major.
* Added length validation for title, first- and last name to `Checkout/Customer/Validation/CustomerProfileValidationFactory.php`.
___
# Storefront
* Added prefixed title, first- and last name validation with error messages to `component/address/address-personal.html.twig` with next major.
