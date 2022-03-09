---
title: Add `ResetInterface` to stateful services.
issue: NEXT-20253
---
# Core
* Added `ResetInterface` to all services having internal state, that needs to be reset between requests, and tagged them with `kernel.reset` tag.
* Deprecated `\Shopware\Core\Framework\Adapter\Twig\EntityTemplateLoader::clearInternalCache()`, use `reset()` instead.
___
# Storefront
* Added `ResetInterface` to `\Shopware\Storefront\Theme\StorefrontPluginRegistry` and tagged it with the `kernel.reset` tag.
___
# Next Major Version Changes
## Removal of `\Shopware\Core\Framework\Adapter\Twig\EntityTemplateLoader::clearInternalCache()`

We removed `\Shopware\Core\Framework\Adapter\Twig\EntityTemplateLoader::clearInternalCache()`, use `reset()` instead.
