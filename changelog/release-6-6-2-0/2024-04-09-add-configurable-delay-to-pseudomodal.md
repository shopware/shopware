---
title: Add configurable delay to pseudoModal
issue: NEXT-34798
---
# Storefront
* Changed `PseudoModalUtil.open()` and re-implemented the default timeout `REMOVE_BACKDROP_DELAY` and added new optional parameter `delay` to overwrite it if needed `.open(callbackFn, delay)`.