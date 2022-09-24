---
title: Add administration login as customer
author: Ugurkan Kaya
author_github: ugurkankya
---
# Core
* Added `Core/Checkout/Customer/Exception/InvalidLoginAsCustomerTokenException.php`
* Added `Core/Checkout/Customer/LoginAsCustomerTokenGenerator.php` for generating secure token for the storefront to identify the customer
* Added `loginAsCustomerTokenGenerate` to `Core/Framework/Api/Controller/SalesChannelProxyController.php`
* Added `Core/Framework/Api/Exception/InvalidCustomerIdException.php`

# Storefront
* Added `loginAsCustomer` to `Storefront/Controller/AuthController.php` for allowing to log in as customer

# Administration
* Added new block `sw_customer_detail_actions_storefront_customer_login` in `module/sw-customer/page/sw-customer-detail/sw-customer-detail.html.twig`
* Added new method `onLoginAsCustomerButtonClick` in `module/sw-customer/page/sw-customer-detail/index.js`
* Added new method `loginAsCustomerTokenGenerate` in `core/service/api/store-context.api.service.js`
* Added new snippet `buttonLoginAsCustomer` in `module/sw-customer/snippet/de-DE.json`
* Added new snippet `notificationLoginAsCustomerErrorMessage` in `module/sw-customer/snippet/de-DE.json`
* Added new snippet `buttonLoginAsCustomer` in `module/sw-customer/snippet/en-GB.json`
* Added new snippet `notificationLoginAsCustomerErrorMessage` in `module/sw-customer/snippet/en-GB.json`
