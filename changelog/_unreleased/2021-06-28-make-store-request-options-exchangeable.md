---
title: Make store request options exchangeable
issue: NEXT-12609
---
# Core
* Added abstract class `Shopware\Core\Framework\Store\Authentication\AbstractStoreRequestOptionsProvider`.
* Added `Shopware\Core\Framework\Store\Authentication\StoreRequestOptionsProvider` to provide header and query parameter for store requests.
* Added `Shopware\Core\Framework\Store\Authentication\FrwRequestOptionsProvider` to provide header and query parameters for first run wizard api.
* Added `Shopware\Core\Framework\Store\Services\InstanceService` to provide shopwareVersion and instanceId
* Added `Shopware\Core\Framework\Store\Authentication\LocaleProvider` to provide the locale of the current user in requests.
* Changed super class from `AbstractStoreController` to `AbstractController` for `FirstRunWizardController`.
* Changed super class from `AbstractStoreController` to `AbstractController` for `StoreController`.
* Changed behaviour of `FirstRunWizardClient::frwLogin` and `FirstRunWizardClient::upgradeAccessToken`. Both update the users store token now automatically.
* Changed behaviour of `StoreClient::loginWithShopwareId`. It updates the users store token now automatically.
* Removed `fireTrackingEvent` from `Shopware\Core\Framework\Store\Services\StoreService`.
* Removed constructor argument `client` from `Shopware\Core\Framework\Store\Services\StoreService`.
* Removed `final` keyword of constructor for `Shopware\Core\Framework\Store\Services\StoreClient`
* Deprecated `Shopware\Core\Framework\Store\Services\StoreService::getDefaultQueryParameters`. Use `Shopware\Core\Framework\Store\Services\StoreService::getDefaultQueryParametersFromContext` instead.
* Deprecated `Shopware\Core\Framework\Store\Services\StoreService::getShopwareVersion`. Use `Shopware\Core\Framework\Store\Services\InstanceService::getShopwareVersion` instead.
* Deprecated `Shopware\Core\Framework\Store\Api\AbstractStoreController`. It will be removed without any replacement.
* Deprecated `Shopware\Core\Framework\Store\Authentication\AbstractStoreRequestOptionsProvider::getDefaultQueryParameters`. In the future this function takes an `Context` object as it's only parameter.