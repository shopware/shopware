---
title: Removed customer default payment method
issue: NEXT-21275
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
author_github: @mstegmeyer
---
# Core
* Deprecated `defaultPaymentMethod` and `defaultPaymentMethodId` in `CustomerEntity`
* Changed `Shopware\Core\Checkout\Cart\Order\OrderConverter` to reliably save the last used payment method
* Deprecated route `store-api.account.set.payment-method` (`POST /store-api/account/change-payment-method/{paymentMethodId}`)
* Deprecated `Shopware\Core\Checkout\Customer\SalesChannel\ChangePaymentMethodRoute`
* Deprecated `Shopware\Core\Checkout\Customer\Event\CustomerChangedPaymentMethodEvent`

___
# Storefront
* Deprecated template `storefront/page/account/payment/index.html.twig`
* Deprecated block `page_account_overview_payment` and its children in template `storefront/page/account/index.html.twig`
* Deprecated block `page_account_sidebar_link_payment` in template `storefront/page/account/sidebar.html.twig`
* Deprecated `\Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPage`
* Deprecated `\Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoadedEvent`
* Deprecated `\Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoadedHook`
* Deprecated `\Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoader`
* Deprecated scripting hook `account-payment-method-page-loaded`
* Deprecated route `frontend.account.payment.page` (`GET /account/payment`)
* Deprecated route `frontend.account.payment.save` (`POST /account/payment`)

___
# Next Major Version Changes

## Customer: Default payment method removed
* Removed default payment method from customer entity, since it was mostly overriden by old saved contexts
* Logic is now more consistent to always be the last used payment method

## Rule builder: Condition `customerDefaultPaymentMethod` removed
* Removed condition `customerDefaultPaymentMethod` from rule builder, since customers do not have default payment methods anymore
* Existing rules with this condition will be automatically migrated to the new condition `paymentMethod`, so the currently selected payment method

## Flow builder: Trigger `checkout.customer.changed-payment-method` removed
* Removed trigger `checkout.customer.changed-payment-method` from flow builder, since customers do not have default payment methods anymore
* Existing flows will be automatically disabled with Shopware 6.7 and removed in a future, destructive migration
