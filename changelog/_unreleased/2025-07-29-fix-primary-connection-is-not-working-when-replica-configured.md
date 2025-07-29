---
title: Fix primary connection is not working when replica configured
issue: https://github.com/shopware/shopware/issues/11085
---
# Core
* Changed `Shopware\Core\Framework\Adapter\Database\MySQLFactory` to parse the primary connection dsn similarly to the replica dsn.
