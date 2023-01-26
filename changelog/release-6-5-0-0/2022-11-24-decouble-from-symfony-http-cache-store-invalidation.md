---
title: Decouple from Symfony HTTP Cache Store Invalidations
issue: NEXT-24311
---
# Storefront

* Symfony triggers on each POST request a HTTP Cache invalidation, this is unwanted and we use tag based invalidation only.  See [issue](https://github.com/symfony/symfony/issues/48301)
