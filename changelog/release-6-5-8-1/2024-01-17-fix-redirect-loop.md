---
title: Fix redirect loop
issue: NEXT-30261
---

# Core

* Changed `Shopware\Core\Framework\Adapter\Kernel\HttpCacheKernel` to do nothing, when the entrypoint of the application was not called through the new `KernelFactory`
* Changed `Shopware\Core\Framework\Adapter\Kernel\HttpKernel` to do nothing, when the entrypoint of the application was not called through the new `KernelFactory`
