---
title: Allow disabling fine grained config caching
issue: NEXT-26840
---

# Core

* Added new config `shopware.cache.tagging.each_config` to disable fine-grained config caching. 
  * When disabled, only a marker will be saved as tag like `system-config` instead of the used exact configs `config.shopName` and so on. 
  * This helps to reduce the cache tags, but the drawback is that any config change will invalidate all pages.
