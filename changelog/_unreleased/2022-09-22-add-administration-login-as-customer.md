---
title: Add login as a customer to the administration
issue: NEXT-8593
author: Ugurkan Kaya, Jan-Erik Spreng
author_github: ugurkankya, sobyte
---
# Core
* Added `Core/Checkout/Customer/CustomerException.php` for base exception factory class
* Added `Core/Checkout/Customer/Exception/InvalidLoginAsCustomerTokenException.php`
* Added `Core/Checkout/Customer/LoginAsCustomerTokenGenerator.php` for generating secure token for the storefront to identify the customer
* Added `Core/Checkout/Customer/SalesChannel/AbstractLoginAsCustomerRoute.php`
* Added `Core/Checkout/Customer/SalesChannel/LoginAsCustomerRoute.php`
* Added `/api/_proxy/login-as-customer-token-generate` to `Core/Framework/Api/Controller/SalesChannelProxyController.php`
* Added `Core/Framework/Api/Exception/InvalidCustomerIdException.php`
___
# Storefront
* Added `/account/login/customer/{token}/{salesChannelId}/{customerId}` to `Storefront/Controller/AuthController.php` for allowing to log in as customer
___
# Store API
* Added `/store-api/account/login/customer` for allowing to log in as customer and returning new token
___
# Administration
* Added new modal in `module/sw-customer/component/sw-customer-login-as-customer-modal/index.js`
* Added `module/sw-customer/component/sw-customer-login-as-customer-modal/sw-customer-login-as-customer-modal.html.twig`
* Added `module/sw-customer/component/sw-customer-login-as-customer-modal/sw-customer-login-as-customer-modal.scss`
* Added new block `sw_customer_login_as_customer_modal` in `module/sw-customer/page/sw-customer-detail/sw-customer-detail.html.twig`
* Added new block `sw_customer_detail_actions_storefront_customer_login` in `module/sw-customer/page/sw-customer-detail/sw-customer-detail.html.twig`
* Added new method `onClickButtonShowLoginAsCustomerModal` in `module/sw-customer/page/sw-customer-detail/index.js`
* Added new method `onClickButtonCloseLoginAsCustomerModal` in `module/sw-customer/page/sw-customer-detail/index.js`
* Added new method `loginAsCustomerTokenGenerate` in `core/service/api/store-context.api.service.js`
* Added new snippet key `loginAsCustomerModal` in `module/sw-customer/snippet/de-DE.json`
* Added new snippet `buttonLoginAsCustomer` in `module/sw-customer/snippet/de-DE.json`
* Added new snippet `notificationLoginAsCustomerErrorMessage` in `module/sw-customer/snippet/de-DE.json`
* Added new snippet key `loginAsCustomerModal` in `module/sw-customer/snippet/en-GB.json`
* Added new snippet `buttonLoginAsCustomer` in `module/sw-customer/snippet/en-GB.json`
* Added new snippet `notificationLoginAsCustomerErrorMessage` in `module/sw-customer/snippet/en-GB.json`
