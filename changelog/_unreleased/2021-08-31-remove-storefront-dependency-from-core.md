---
title: Remove Storefront dependency from Core
issue: NEXT-8572
---
# API
* Changed route `api.custom.updateapi.finish` to only return redirect to administration if the administration is installed, otherwise status 204 (NO_CONTENT) will be returned
* Changed route `api.action.captcha.list` to be only available if storefront is installed, otherwise it will result in a 404
___
# Administration
* Deprecated `\Shopware\Administration\Service\AdminOrderCartService`, use `\Shopware\Core\Checkout\Cart\ApiOrderCartService` instead
___
# Core
* Added `\Shopware\Core\Checkout\Cart\ApiOrderCartService`
* Changed `\Shopware\Core\Framework\Api\Controller\SalesChannelProxyController` to use `ApiOrderCartService` instead of the deprecated `AdminOrderCartService`
* Added `\Shopware\Core\Checkout\Customer\Event\AddressListingCriteriaEvent`
* Changed `\Shopware\Core\Checkout\Customer\SalesChannel\ListAddressRoute` to additionally dispatch the new `\Shopware\Core\Checkout\Customer\Event\AddressListingCriteriaEvent`
* Added `\Shopware\Core\Content\ProductExport\Event\ProductExportContentTypeEvent`
* Changed `\Shopware\Core\Content\ProductExport\SalesChannel\ExportController` to additionally dispatch the new `\Shopware\Core\Content\ProductExport\Event\ProductExportContentTypeEvent`
* Changed `\Shopware\Core\DevOps\System\Command\SystemInstallCommand` to only execute tasks that are available in the installation
* Deprecated `\Shopware\Core\Framework\Adapter\Asset\ThemeAssetPackage`, use `\Shopware\Storefront\Theme\ThemeAssetPackage` instead
* Added `\Shopware\Core\Framework\Adapter\Twig\Extension\SwSanitizeTwigFilter`
* Changed `\Shopware\Core\Framework\App\AppUrlChangeResolver\UninstallAppsStrategy` to make dependency on `ThemeAppLifecycleHandler` optional
* Changed `\Shopware\Core\Framework\Store\Helper\PermissionCategorization` to use private constants, instead of depending on the storefront
* Changed `\Shopware\Core\Framework\Store\Services\ExtensionLoader` to make dependency on `theme.repository` optional
* Changed `\Shopware\Core\Framework\Store\Services\StoreAppLifecycleService` to make dependency on `theme.repository` optional
* Changed `\Shopware\Core\Framework\Update\Api\UpdateController` to only redirect to the administration, if the administration is installed on finish request
* Changed `\Shopware\Core\System\User\Recovery\UserRecoveryService` to fallback on `APP_URL` when generating the administration url
* Changed `\Shopware\Core\HttpKernel` to only use HttpCache if it is available (storefront is installed)
* Removed `\Shopware\Core\Framework\Api\Controller\CaptchaController`
___
# Storefront
* Deprecated `\Shopware\Storefront\Page\Address\Listing\AddressListingCriteriaEvent`, use `\Shopware\Core\Checkout\Customer\Event\AddressListingCriteriaEvent` instead
* Deprecated `\Shopware\Storefront\Event\ProductExportContentTypeEvent`, use `\Shopware\Core\Content\ProductExport\Event\ProductExportContentTypeEvent` instead
* Added `\Shopware\Storefront\Theme\ThemeAssetPackage`
* Removed `\Shopware\Storefront\Framework\Twig\Extension\SwSanitizeTwigFilter`
* Added `\Shopware\Storefront\Controller\Api\CaptchaController`
* Changed `\Shopware\Storefront\Framework\Cache\CacheResponseSubscriber` to add HttpCache-Annotation to cached core routes
___
# Upgrade Information
## Deprecation of AdminOrderCartService

The `\Shopware\Administration\Service\AdminOrderCartService` was deprecated and will be removed in v6.5.0.0, please use the newly added `\Shopware\Core\Checkout\Cart\ApiOrderCartService` instead. 

## Deprecation of Shopware\Storefront\Page\Address\Listing\AddressListingCriteriaEvent

The `\Shopware\Storefront\Page\Address\Listing\AddressListingCriteriaEvent` was deprecated and will be removed in v6.5.0.0, if you subscribed to the event please use the newly added `\Shopware\Core\Checkout\Customer\Event\AddressListingCriteriaEvent` instead.

## Deprecation of Shopware\Storefront\Event\ProductExportContentTypeEvent

The `\Shopware\Storefront\Event\ProductExportContentTypeEvent` was deprecated and will be removed in v6.5.0.0, if you subscribed to the event please use the newly added `\Shopware\Core\Content\ProductExport\Event\ProductExportContentTypeEvent` instead.

## Deprecation of Shopware\Core\Framework\Adapter\Asset\ThemeAssetPackage

The `\Shopware\Core\Framework\Adapter\Asset\ThemeAssetPackage` was deprecated and will be removed in v6.5.0.0, please use the newly added `\Shopware\Storefront\Theme\ThemeAssetPackage` instead. 
