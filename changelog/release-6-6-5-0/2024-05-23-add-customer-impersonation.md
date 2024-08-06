---
title: Add customer impersonation
issue: NEXT-36854
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Added `Core/Checkout/Customer/CustomerException::invalidImitationToken` exception
* Added `Core/Checkout/Customer/Exception/InvalidImitateCustomerTokenException`
* Added `Core/Checkout/Customer/ImitateCustomerTokenGenerator` for generating a secure token to imitate a customer
* Added `Core/Checkout/Customer/SalesChannel/AbstractImitateCustomerRoute`
* Added `Core/Checkout/Customer/SalesChannel/ImitateCustomerRoute`
* Added `Core/Checkout/Customer/Subscriber/CustomerLogoutSubscriber`
* Added `/api/_proxy/generate-imitate-customer-token` to `Core/Framework/Api/Controller/SalesChannelProxyController`
* Changed `Core/Framework/Api/ApiDefinition/Generator/Schema/StoreApi/paths/account.json` to include the `account/login/imitate-customer` route
* Changed `Core/System/SalesChannel/Context/SalesChannelContextFactory` to check for the `IMITATING_USER_ID`
* Changed `Core/System/SalesChannel/Context/SalesChannelContextService` to add the const `IMITATING_USER_ID` and check for it
* Changed `Core/System/SalesChannel/Context/SalesChannelContextServiceParameters` to add the `imitatingUserId` parameter
* Changed `Core/System/SalesChannel/Context/CartRestorer` to update the `imitatingUserId`
* Changed `Core/System/SalesChannel/SalesChannelContext` to include the `imitatingUserId` variable
* Changed `Core/Framework/Routing/SalesChannelRequestContextResolver` to get the `ATTRIBUTE_IMITATING_USER_ID` from session
* Changed `Core/PlatformRequest` to include the `ATTRIBUTE_IMITATING_USER_ID` (`sw-imitating-user-id`)
___
# Storefront
* Added `/account/login/imitate-customer` to `Storefront/Controller/AuthController` for allowing to imitate a customer
___
# Store API
* Added `/store-api/account/login/imitate-customer` for allowing to imitate a customer and returning the new token
___
# Administration
* Added new modal in `module/sw-customer/component/sw-customer-imitate-customer-modal/index.js`
* Added `module/sw-customer/component/sw-customer-Imitate-customer-modal/sw-customer-imitate-customer-modal.html.twig`
* Added `module/sw-customer/component/sw-customer-imitate-customer-modal/sw-customer-imitate-customer-modal.scss`
* Added new block `sw_customer_card_imitate_customer_modal` in `module/sw-customer/component/sw-customer-card/sw-customer-card.html.twig`
* Added new block `sw_customer_card_action_customer_impersonation` in `module/sw-customer/component/sw-customer-card/sw-customer-card.html.twig`
* Added new method `generateImitateCustomerToken` in `core/service/api/store-context.api.service.ts`
* Added new method `redirectToSalesChannelUrl` in `core/service/api/store-context.api.service.ts`
* Added `imitateCustomerModal` in `module/sw-customer/snippet/de-DE.json`
* Added `buttonImitateCustomer` in `module/sw-customer/snippet/de-DE.json`
* Added `notificationImitateCustomerErrorMessage` in `module/sw-customer/snippet/de-DE.json`
* Added `imitateCustomerModal` in `module/sw-customer/snippet/en-GB.json`
* Added `buttonImitateCustomer` in `module/sw-customer/snippet/en-GB.json`
* Added `notificationImitateCustomerErrorMessage` in `module/sw-customer/snippet/en-GB.json`
* Added `api_proxy_imitate-customer` permission
* Added `api_proxy_imitate-customer` in `src/module/sw-users-permissions/snippet/de-DE.json`
* Added `api_proxy_imitate-customer` in `src/module/sw-users-permissions/snippet/en-GB.json`
