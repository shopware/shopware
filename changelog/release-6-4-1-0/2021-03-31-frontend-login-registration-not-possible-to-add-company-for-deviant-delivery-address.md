---
title: frontend Login-Registration not possible to add company for deviant delivery address
issue: NEXT-14213
---
# Core
*  Changed function `validateRegistrationData` at `Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute` to add validator for shipping Address Company when `$data['shippingAddress']['accountType'] === 'business'` 
___
# Storefront
*  Added twig variable `customToggleTarget` to `/src/Storefront/Resources/views/storefront/component/address/address-personal.html.twig` to custom toggle field target
*  Added twig variable `customToggleTarget` to `/src/Storefront/Resources/views/storefront/component/address/address-personal-company.html.twig` to custom toggle field target
*  Changed `hideCustomerTypeSelect` at block `component_account_register_address_shipping_fields` at `/src/Storefront/Resources/views/storefront/component/account/register.html.twig` to `false`
*  Changed `customToggleTarget` at block `component_account_register_address_shipping_fields` at `/src/Storefront/Resources/views/storefront/component/account/register.html.twig` to `true`
*  Changed `customToggleTarget` at block `component_account_register_address_shipping_fields_company` at `/src/Storefront/Resources/views/storefront/component/account/register.html.twig` to `true`
*  Changed `/src/Storefront/Resources/views/storefront/component/address/address-personal-company.html.twig` to be able to display Company section when `prefix === 'shippingAddress'`
*  Changed block `component_address_form_company_vatId` from `/src/Storefront/Resources/views/storefront/component/address/address-personal-company.html.twig` to be able to only display VatIds section when `prefix != 'shippingAddress'`
