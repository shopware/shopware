---
title: Changed hash algo for cache
issue: NEXT-38366
author: tinect
author_email: s.koenig@tinect.de
author_github: tinect
---

# Core

* Changed hash algo for cache key generation from sha256 to xxh128 in \Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator
* Changed hash algo for cache key generation from sha256 to xxh128 in \Shopware\Core\Framework\Adapter\Twig\ConfigurableFilesystemCache

