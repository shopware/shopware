---
title: Add storefront endpoints for app scripts
issue: NEXT-19568
---
# Storefront
* Added `\Shopware\Storefront\Framework\Script\Api\StorefrontHook` to provide the functionality to add custom endpoints in the storefront via scripts.
* Added `\Shopware\Core\Framework\Script\Api\ScriptResponseFactoryFacade` to create responses for custom-endpoint scripts.
* Added `\Shopware\Core\System\SalesChannel\SalesChannelContext::ensureLoggedIn()` helper method, to throw a `CustomerNotLoggedInException` if the customer is not logged in.
