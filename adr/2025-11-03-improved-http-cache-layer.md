---
title: Improved HTTP Cache Layer
date: 2025-11-03
area: core
tags: [core, performance, caching, reverse-proxy, developer-experience]
---

## Context

Shopware currently supports an HTTP-cache layer; however, the current implementation has some limitations:
* **Only storefront requests are cached**: The cache is only used for storefront requests, store-api is not supported out of the box, leading to performance penalties in headless projects.
* **Cache-Hit rate is rather low**: All matched rule ids are included in the cache-hash, this leads to a lot of cache permutations. As one consequence of that, by default, the whole caching is disabled as soon as the cart is filled or a customer logged in.
* **Complex reverse proxy configuration**: The reverse proxy configuration is quite complex because of the use of different cache headers and cookies, as a result we only support Fastly and Varnish, other reverse proxies are hard to add.
* **Actual cache-control configuration is hard-coded and splattered**: The values set for `cache-control` headers are hard-coded and splattered (e.g., hardcoded in the reverse proxy config and in shopware), they cannot be configured based on projects needs, and also on route level only the max-age is configurable.

## Decision

We will rework the HTTP-cache layer to address the limitations mentioned above, for that the following changes will be made:

### Only use cache-relevant rule-ids inside cache-hash

Most rule-ids are not relevant for caching, e.g., when they are not used at all, or only for checkout-related features (e.g., payment-methods, promotions).
Therefore, we will only include the rule-ids that are relevant for caching in the cache-hash, we do the distinction based on the existing `rule-areas`.
By default, all rule-ids, that are used in the `product` area, will be included in the cache-hash. For extensibility, a new event will be introduced that allows modifying the list of rule-areas that are relevant for caching.

### Configurable caching policies

The caching policies used will be moved from the code directly to the configuration. We will allow defining default caching policies based on the area (storefront or store-api), as well as use-case (cached or uncached).
We also allow route level configuration that will override the default caching policies.

### Simplify reverse proxy configuration

We will simplify the reverse proxy configuration by only relying on the `sw-cache-hash`, as the only application state that the reverse proxy needs to take into account.
The `sw-cache-hash` will be set as a cookie and as a header, additionally we add the `sw-cache-hash` header name as a `vary` header to the response. So the response headers will look like this:
```
sw-cache-hash: theHash
vary: sw-cache-hash
set-cookie: sw-cache-hash=theHash;
```
Adding it as header allows the default [`vary` header](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Vary) implementation of reverse proxies to work.
The cookie is used to make it easier for clients to pass along the correct hash with all further requests, without the need to manually handle the header.
The only shopware specific reverse proxy configuration will be to set the `sw-cache-hash` header based on the `sw-cache-hash` cookie on the reverse proxy.

Additionally, the use of clear and configurable policies for the cache-control headers will remove the need to manually override the cache-control headers on the reverse proxy side.

### HTTP-Cache support for store-api

We will add HTTP-Cache support for the store-api as well. The caching behaviour and used patterns are the same as for the storefront.
So the configuration (on the shopware side, as well as on the reverse proxy) will be the same as in the storefront.
To make the caching applicable for the store-api, we will adjust the store-api routes to support `GET` requests where it makes sense, clients should preferably use the `GET` requests.

For detailed documentation on why and how we added support for the store-api caching, refer to the [specific store-api caching ADR](./2025-09-15-store-api-cache-strategy.md).

## Consequences

### Storefront pages for logged-in users and filled carts are cached by default

By removal of the state header, those pages will be cached by default. 
This might be a breaking change for some projects when their customizations rely on the cache not being used for logged-in users or filled carts.
This is especially the case when context/user-specific information is used in the template. In those cases the more dynamic content should be refetched over async AJAX calls to uncached routes, so that the main content can still be cached.

### Not all rules influence the cache-hash

As only the rules used in some rule areas will be included in the cache-hash, some rules will not influence the cache-hash.
This could be a breaking change for some projects, when they built customization based on rules but did not include them in the rule-areas.

### Store-API routes will be cached by default

The store-api routes will be cached by default; this might be a breaking change for some projects when they rely on the cache not being used for store-api routes.
Also, they need to adjust their clients to correctly pass the cache-hash head or cookie, otherwise it could lead to the wrong data being returned from the cache.

### Feature flag handling

All the breaking changes (and caching benefits) can be already used by opting in and enabling the `CACHE_REWORK` feature flag.