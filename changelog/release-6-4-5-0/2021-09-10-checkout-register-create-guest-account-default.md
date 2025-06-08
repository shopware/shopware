---
title: Checkout register create guest account default
issue: NEXT-16236
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com 
author_github: seggewiss
---
# Core
* Added `createCustomerAccountDefault` bool input field to system config domain `core.loginRegistration` 
___
# Storefront
* Added block `component_account_register_create_account` to `views/storefront/component/account/register.html.twig`
* Changed request parameter which decides if a customer account is created from `guest` to `createCustomerAccount` for `\Shopware\Storefront\Controller\RegisterController::register`
* Changed text for `general.privacyNotice` snippet
___
# Upgrade Information

## RegisterController::register

Registering a customer with `\Shopware\Storefront\Controller\RegisterController::register` now requires the request parameter `createCustomerAccount` to create a customer account.
If you dont specify this parameter a guest account will be created.
