---
title: Deprecate implicit HTTP cache clearing from cache:clear command and introduce explicit cache:clear:http command
issue: NEXT-39671
---
# Core
* Deprecated `\Shopware\Core\Framework\Adapter\Cache\ReverseProxy\ReverseProxyCacheClearer` so that HTTP cache is not cleared with the `cache:clear` command 
* Added `cache:clear:http` to explicitly clear the HTTP cache
___
# Upgrade Information
## Deprecation of ReverseProxyCacheClearer

The `\Shopware\Core\Framework\Adapter\Cache\ReverseProxy\ReverseProxyCacheClearer` will be removed with the next major version.

If you relied on the `cache:clear` command to clear your HTTP cache, you should use the `cache:clear:http` command additionally.
However, unless you enable the `v6.7.0.0` feature flag, HTTP cache will still be cleared on `cache:clear`
___
# Next Major Version Changes
## Removal of ReverseProxyCacheClearer

The `\Shopware\Core\Framework\Adapter\Cache\ReverseProxy\ReverseProxyCacheClearer` was removed.

Use the `cache:clear:http` command to clear the HTTP cache explicitly, as it is no longer done with the `cache:clear` command.
