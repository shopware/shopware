---
title: Remove core dependencies from ContextController
issue: NEXT-21967
author: Stefan Sluiter
author_email: s.sluiter@shopware.com
author_github: ssltg
---
# Core
* Added `Shopware\Core\Checkout\Customer\SalesChannel\AbstractChangeLanguageRoute`
* Added `Shopware\Core\Checkout\Customer\SalesChannel\ChangeLanguageRoute`
* Added new store-api route `/account/change-language`
* Changed `\Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute::switchContext` to check return the new domain on language change.
* Added `getRedirectUrl` to `Shopware\Core\System\SalesChannel\ContextTokenResponse` to hold the redirectUrl in a token change if necessary. 
___
# Storefront
* Changed `Shopware\Storefront\Controller\ContextController` to use `ChangeLanguageRoute` and `ContextSwitchRoute` instead of repositories.
* Deprecated `ChangeLanguageRoute` in `Shopware\Storefront\Controller\ContextController`. This will be removed in v6.5.0.0.
* Deprecated the automatic change of the customers language on the change of the storefront language in `\Shopware\Storefront\Controller\ContextController::switchLanguage`