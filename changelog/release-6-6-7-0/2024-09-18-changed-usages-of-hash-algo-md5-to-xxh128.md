---
title: Changed usages of hash algo md5 to xxh128
issue: NEXT-38371
author: tinect
author_email: s.koenig@tinect.de
author_github: tinect
---

# Core

* Changed usages of hash algo md5 to xxh128 in several places
* Changed the Kernel cache hash generation to not hash plugin data which gets hashed again and removed the substr of data
* Added a Util class to generate hashes, defaulting to use xxh128
* Changed hash algo for cache key generation from sha256 to use new util class with default xxh128 in \Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator and \Shopware\Core\Framework\Adapter\Twig\ConfigurableFilesystemCache
