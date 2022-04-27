---
title: Added REDIS_PREFIX env variable
issue: NEXT-20971
---
# Core
* Added `REDIS_PREFIX` env variable which allows to prefix all redis keys created by shopware
* Removed `EntityDefinition` type hint in `IteratorFactory::createIterator` and allows to provide only the entity name `IteratorFactory::createIterator('product')`  
* Deprecated `\Shopware\Core\Framework\Adapter\Cache\RedisConnectionFactory::createConnection`, use `\Shopware\Core\Framework\Adapter\Cache\RedisConnectionFactory::create` instead