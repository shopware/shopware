---
title: Remove core dependencies from CountryStateController
issue: NEXT-21967
author: Stefan Sluiter
author_email: s.sluiter@shopware.com
author_github: ssltg
---
# Core
* Changed `Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber` by adding new invalidation for country_states
* Added `Shopware\Core\System\Country\Event\CountryStateRouteCacheKeyEvent`
* Added `Shopware\Core\System\Country\Event\CountryStateRouteCacheTagsEvent`
* Added `Shopware\Core\System\Country\SalesChannel\AbstractCountryStateRoute`
* Added `Shopware\Core\System\Country\SalesChannel\CountryStateRoute`
* Added new Store-Api Route `/country-state` to get all states of a given countryId
* Added `Shopware\Core\System\Country\SalesChannel\CachedCountryStateRoute`
* Added `Shopware\Core\System\Country\SalesChannel\CountryStateRouteResponse`
___
# Storefront
* Added `Shopware\Storefront\Pagelet\Country\CountryStateDataPagelet`
* Added `Shopware\Storefront\Pagelet\Country\CountryStateDataPageletCriteriaEvent`
* Added `Shopware\Storefront\Pagelet\Country\CountryStateDataPageletLoadedHook` with hook name `country-sate-data-pagelet-loaded`
* Added `Shopware\Storefront\Pagelet\Country\CountryStateDataPageletLoadedEvent`
* Added `Shopware\Storefront\Pagelet\Country\CountryStateDataPageletLoader`
* Changed `Shopware\Storefront\Controller\CountryStateController` to use `Shopware\Storefront\Pagelet\Country\CountryStateDataPageletLoader`
* Changed method `requestStateData` to use `stateRequired` as third parameter in `/Storefront/Resources/app/storefront/src/plugin/forms/form-country-state-select.plugin.js`
* Changed options of `AddressCountry`-select by adding a new data value `data-state-required` in `Storefront/Resources/views/storefront/component/address/address-form.html.twig`
* Deprecated `$countryRoute` in constructor of `Shopware\Storefront\Controller\CountryStateController`
* Deprecated `stateRequired` as Response in `\Shopware\Storefront\Controller\CountryStateController::getCountryData`
___
# Upgrade Information
## Update `requestStateData` method in `form-country-state-select.plugin.js`
The method `requestStateData` will require the third parameter `stateRequired` to be set from the calling instance.
It will no longer be provided by the endpoint of `frontend.country.country-data`.
The value can be taken from the selected country option in `data-state-required` 