---
title: Remove core dependencies from AuthController
issue: NEXT-21967
author: Stefan Sluiter
author_email: s.sluiter@shopware.com
author_github: ssltg
---
# Core
* Added `Shopware\Core\Checkout\Customer\SalesChannel\AbstractCustomerRecoveryIsExpiredRoute`
* Added `Shopware\Core\Checkout\Customer\SalesChannel\CustomerRecoveryIsExpiredResponse`
* Added `Shopware\Core\Checkout\Customer\SalesChannel\CustomerRecoveryIsExpiredRoute`
* Added new store-api route `/account/customer-recovery-is-expired`
___
# Storefront
* Added `Shopware\Storefront\Page\Account\RecoverPassword\AccountRecoverPasswordPage`
* Added `Shopware\Storefront\Page\Account\RecoverPassword\AccountRecoverPasswordPageLoadedEvent`
* Added `Shopware\Storefront\Page\Account\RecoverPassword\AccountRecoverPasswordPageLoadedHook` with hook name `account-recover-password-page-loaded`
* Added `\Shopware\Storefront\Page\Account\RecoverPassword\AccountRecoverPasswordPageLoader`
* Deprecated `hash` as single value for rendering of `Storefront/Resources/views/storefront/page/account/profile/reset-password.html.twig`. Use `page.getHash` from `v6.5.0.0` instead.
* Deprecated page type for `Storefront/Resources/views/storefront/page/account/profile/reset-password.html.twig` new type in `v6.5.0.0` will be `Shopware\Storefront\Page\Account\RecoverPassword\AccountRecoverPasswordPage`. As a result page.salutations and page.countries will no longer be available in the page object.
* Changed `Shopware\Storefront\Controller\AuthController` to use `StorefrontCartFacade` and `AccountRecoverPasswordPageLoader` and removed the `customerRecoveryRepository`.
