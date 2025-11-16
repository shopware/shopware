## HTTP cache policies - current implementation

### Overview
- Cache policies are defined in configuration and applied by `CacheResponseSubscriber` to form Cache-Control (and related) headers.
- Policies can be referenced as defaults per area (`storefront`, `store_api`) and overridden per route.
- When the `HTTP_CACHE_POLICIES` feature is enabled (default in tests), the subscriber uses policies; otherwise legacy behavior is used.

### Configuration keys (shopware.http_cache)
- `policies`: map of policyName -> configuration
  - Structure: `{ headers: { cache_control?: { directives } } }`
  - Supported directives: `public`, `private`, `no_cache`, `no_store`, `no_transform`, `must_revalidate`, `proxy_revalidate`, `immutable`, `max_age`, `s_maxage`, `stale_while_revalidate`, `stale_if_error`.
  - Example:
    ```yaml
    shopware:
      http_cache:
        policies:
          no_cache_private:
            headers:
              cache_control:
                private: true
                no_cache: true
                max_age: 0
                s_maxage: 0
          store_api.cacheable:
            headers:
              cache_control:
                public: true
                s_maxage: 0
                stale_while_revalidate: 3600
                stale_if_error: 7200
          storefront.cacheable:
            headers:
              cache_control:
                public: true
                max_age: 600
                s_maxage: 3600
                stale_while_revalidate: 60
                stale_if_error: 300
    ```
- `default_policies`: defaults per area
  - Structure: `{ <area>: { cacheable?: string, uncacheable?: string } }`
  - Areas supported and shipped by default: `storefront`, `store_api`.
  - Example:
    ```yaml
    shopware:
      http_cache:
        default_policies:
          storefront:
            cacheable: storefront.cacheable
            uncacheable: no_cache_private
          store_api:
            cacheable: store_api.cacheable
            uncacheable: no_cache_private
    ```
- `route_policies`: map of routeName -> policyName to override defaults
  - Supports granular overrides per script hook using `route#hook` pattern for script endpoints
  - Example:
    ```yaml
    shopware:
      http_cache:
        route_policies:
          # Route-level override (applies to all scripts on this route)
          store-api.product.search: p_route_override
          
          # Granular per-script overrides using route#hook pattern
          # Format: route#normalized-hook-name
          frontend.script_endpoint: storefront.cacheable
          frontend.script_endpoint#storefront-acme-feature: storefront.cacheable_fast
          frontend.script_endpoint#storefront-vendor-legacy: no_cache_private
          
          store-api.script_endpoint: store_api.cacheable
          store-api.script_endpoint#store-api-acme-feature: store_api.cacheable
    ```
  - Hook normalization: URL path `/storefront/script/acme/feature` becomes `storefront-acme-feature`
  - Hook normalization: URL path `/store-api/script/vendor/action` becomes `store-api-vendor-action`

### Runtime behavior (CacheResponseSubscriber)
- Policy resolution precedence (highest to lowest):
  1. `route_policies[route#hook]` - most specific, for script endpoints with hook
  2. `route_policies[route]` - route-level override
  3. `default_policies[area].{cacheable|uncacheable}` - area defaults. TTLs (maxage, s-maxage) for them are redefined by values from the attribute (which can be set as a route attribute or as a request attribute in ScriptsController)
- Store API (when `HTTP_CACHE_POLICIES` is active):
  - Only GET requests are cacheable; POST and non-GET use `default_policies.store_api.uncacheable`.
  - Requires `PlatformRequest::ATTRIBUTE_HTTP_CACHE` attribute; if absent -> uncacheable policy.
  - Policy is applied directly from the configured policies array using `Response::setCache()`.
  - Removes existing `cache-control` header before applying the policy to avoid mixing directives.
  - Ignores state/cookie logic (no cookies set for Store API when policies are active).
- Storefront (when `HTTP_CACHE_POLICIES` is active):
  - Uncacheable policy is applied for non-GET routes except `frontend.account.login`.
  - If request is marked cacheable and no invalidation state matches, applies resolved policy; otherwise applies `uncacheable`.
  - State and context cookies behavior remains for storefront.
- Legacy path (when `HTTP_CACHE_POLICIES` is disabled):
  - Storefront: legacy cookie/state handling; `setSharedMaxAge($ttl)` which implicitly marks response `public` and adds `s-maxage`, plus `stale-if-error` / `stale-while-revalidate` from container.
  - Store API: when policies are disabled, Store API requests are not handled by the special branch and fall through to regular storefront-like behavior (cookies/states/cart handling).

### JSON schema
- `config-schema.json` updated to document:
  - `http_cache.policies` (with known directive keys),
  - `http_cache.default_policies` (areas -> `cacheable`/`uncacheable`),
  - `http_cache.route_policies` (route -> policy, route#hook -> policy).

### App Script Integration
- App developers control caching behavior via `ResponseCacheConfiguration`:
  - `response.cache.maxAge(seconds)` - set TTL (time to live)
  - `response.cache.invalidationState('logged-in', 'cart-filled')` - define context states when cache should be bypassed
  - `response.cache.tag('tag1', 'tag2')` - add cache tags for selective invalidation
  - `response.cache.disable()` - explicitly disable caching
- Script controllers automatically attach normalized hook name in `ATTRIBUTE_HTTP_CACHE['hook']`
  - Example: `/storefront/script/acme/feature` → `storefront-acme-feature`
  - Example: `/store-api/script/vendor/action` → `store-api-vendor-action`
- Shop administrators override caching policies using `route_policies` with `route#hook` pattern
  - App developers don't reference policy names (they don't know shop configuration)
  - Admins map specific scripts to policies: `frontend.script_endpoint#storefront-acme-feature: storefront.cacheable_fast`
  - This allows per-app/per-script policy control without app changes

### Notes
- Default policies are provided in `src/Core/Framework/Resources/config/packages/shopware.yaml` and can be overridden per environment, but configuration validation disallows referencing undefined policy names.

### Future Header Types
The `headers` structure allows for future cache header types with different directive shapes:
- `cdn_cache_control` (RFC 9213) - for CDN-specific caching policies that differ from browser caching
- `surrogate_control` - for surrogate/edge server caching

Each header type can have different directive shapes appropriate to its specification. For example:
```yaml
shopware:
  http_cache:
    policies:
      storefront.cacheable:
        headers:
          cache_control:        # For browsers and general caches
            public: true
            max_age: 600
            s_maxage: 3600
          cdn_cache_control:    # Future: CDN-specific directives
            public: true
            max_age: 86400
```

