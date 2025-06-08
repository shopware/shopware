---
title: Mark http cache as stale instead deleting
issue: NEXT-22576
---
# Storefront
* Added two config options to set `stale-while-revalidate` and `stale-if-error` for `storefront.http_cache`
  * Both additional header fields are for external reverse proxy caching and will be not used internally by the Symfony http cache component.
