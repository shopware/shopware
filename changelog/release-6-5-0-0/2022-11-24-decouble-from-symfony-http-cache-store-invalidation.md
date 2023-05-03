---
title: Decouple from Symfony HTTP Cache Store Invalidations
issue: NEXT-24311
---
# Storefront
* Removed Symfony HTTP cache invalidation trigger on each POST request as this is unwanted. See [issue](https://github.com/symfony/symfony/issues/48301)
* Changed to tag based cache invalidation.
