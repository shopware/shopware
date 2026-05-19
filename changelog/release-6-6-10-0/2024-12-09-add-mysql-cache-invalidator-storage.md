---
title: Add mysql cache invalidator storage
issue: NEXT-39316
---
# Core
* Added `\Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage\MySQLInvalidatorStorage` to collect invalidations in MySQL in an atomic operation.
___
# Upgrade Information

## Addition of MySQLInvalidatorStorage

We added a new MySQL cache invalidator storage so you can take advantage of delayed cache invalidation without needing Redis (Redis is still preferred).

```yaml
shopware:
    cache:
        invalidation:
            delay: 1
            delay_options:
                storage: mysql
```
