---
title: Fix redirect loop for SEO URLs with query parameters
issue: 10833
---
# Core
* Changed `Shopware\\Core\\Content\\Seo\\SeoResolver::resolve` to consider the request query string when matching stored `seo_path_info`, ensuring exact matches for SEO URLs that include query parameters and preventing redirect loops.
* Deprecated `Shopware\\Core\\Content\\Seo\\AbstractSeoResolver::resolve()` without `$queryString`; an optional 4th parameter `$queryString` will be added in v6.8.
* Changed `Shopware\\Core\\Content\\Seo\\EmptyPathInfoResolver::resolve` to forward the optional `$queryString` to the decorated resolver.
___
# Storefront
* Changed `Shopware\\Storefront\\Framework\\Routing\\RequestTransformer::transform` to forward the request query string to the resolver so exact matching works end-to-end and to set the canonical link from the resolved `canonicalPathInfo` when present.
___
# Upgrade Information
## Prepare for `$queryString` in SEO resolver
If you extend or decorate `AbstractSeoResolver::resolve`, add an optional 4th parameter `?string $queryString = null` and pass it through to the decorated resolver. Existing calls continue to work; this ensures forward compatibility with v6.8.

Before:

```php
public function resolve(string $languageId, string $salesChannelId, string $pathInfo): array
```

After:

```php
public function resolve(string $languageId, string $salesChannelId, string $pathInfo, ?string $queryString = null): array
```
___
# Next Major Version Changes
`AbstractSeoResolver::resolve` adds the optional `?string $queryString = null` 4th parameter in v6.8. Implementations must update their signatures accordingly.

