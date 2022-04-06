---
title: Changed system config memoization to use separate service.
issue: https://github.com/shopware/platform/issues/2319
author: Andreas Allacher
author_email: andreas.allacher@massiveart.com
author_github: @AndreasA
---
# Core
* Changed `Shopware\Core\System\SystemConfig\SystemConfigService` to not memoize the system configuration.
* Added `Shopware\Core\System\SystemConfig\Store\MemoizedSystemConfigStore` to memoize the system configuration and clear it upon changes.
* Added `Shopware\Core\System\SystemConfig\MemoizedSystemConfigLoader` to memoize the system configuration in `Shopware\Core\System\SystemConfig\Store\MemoizedSystemConfigStore`.
