---
title: Implement cache lock
issue: NEXT-20432
---
# Core
* Added `\Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor`, which allows value compression without providing cache items
* Deprecated `\Shopware\Core\Framework\Adapter\Cache\CacheCompressor`, use new `\Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor` instead
___
# Next Major Version Changes
## Cache compressor
* Instead of using the deprecated `\Shopware\Core\Framework\Adapter\Cache\CacheCompressor`, use the new `\Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor`
change
```
CacheCompressor::uncompress($item);
CacheCompressor::compress($item, $value);
```
to
```
CacheValueCompressor::uncompress($item->get());
$item->set(CacheValueCompressor::compress($value));
```