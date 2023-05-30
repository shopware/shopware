---
title: Fix setting null-data while expecting array on a StorefrontResponse
issue: NEXT-24082
author: Stephan Niewerth
author_email: snw@heise.de
author_github: stephanniewerth
---
# Storefront
* Added unit test for StorefrontResponse in `tests/unit/php/Storefront/Framework/Routing/StorefrontResponseTest.php`
* Changed `src/Storefront/Framework/Routing/StorefrontResponse.php` to return an empty array on `getData()` if $data is `null`
* Changed `src/Storefront/Framework/Routing/NotFound/NotFoundSubscriber.php` and `src/Storefront/Framework/Cache/CacheStore.php` to set StorefrontResponse data to an empty array
* Deprecated `setData()` usage as to strictly type parameter `$data` as `array` in `src/Storefront/Framework/Routing/StorefrontResponse.php` with v6.6.0
* Deprecated property `$data` will be natively typed to array and initilized with `[]` in `src/Storefront/Framework/Routing/StorefrontResponse.php` with v6.6.0
* Deprecated property `$context` will be natively typed as `?SalesChannelContext` and initialized with `null` in `src/Storefront/Framework/Routing/StorefrontResponse.php` with v6.6.0
* Deprecated null coalesce in `getData` can be removed if $data is natively typed in `src/Storefront/Framework/Routing/StorefrontResponse.php` with v6.6.0

