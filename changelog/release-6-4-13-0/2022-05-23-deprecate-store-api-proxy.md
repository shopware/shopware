---
title: Deprecate store-api proxy in storefront
issue: NEXT-20396
---
# Storefront
* Deprecated the `/_proxy/store-api`-API route. It will be removed in v6.5.0.0. Please use the store-api directly or add a custom controller instead.
* Deprecated public const `\Shopware\Core\SalesChannelRequest::ATTRIBUTE_STORE_API_PROXY`, it will be removed with the removal of the store-api proxy in v6.5.0.0.
* Deprecated the `store-api-client.service.js` service in the storefront js.
___
# Next Major Version Changes
## Removal of the  `/_proxy/store-api`-API route

The `store-api` proxy route was removed. Please use the store-api directly.
If that is not possible use a custom controller, that calls the `StoreApiRoute` internally.
The `StoreApiClient` class from `storefront/src/service/store-api-client.service.js` was also removed, as that class relied on the proxy route.
