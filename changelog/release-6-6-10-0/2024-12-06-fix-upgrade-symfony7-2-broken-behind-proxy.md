---
title: Fix upgrade symfony7.2 broken behind proxy configuration
issue: NEXT-39965
---
# Core
* Changed the logic in `Shopware\Core\Kernel::boot` to reuse `Symfony\Component\HttpKernel\Kernel::boot`.
