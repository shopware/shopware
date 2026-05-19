---
title: Fix mysql replica parameters parsing
issue: https://github.com/shopware/shopware/issues/11085
---
# Core
* Changed `Shopware\Core\Framework\Adapter\Database\MySQLFactory` to properly initialize replica parameters provided in the dsn format
