---
title: Implement KeyValueStorage
issue: NEXT-28565
---
# Core
* Added new class `\Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage` which allows simple key-value operations
* Added new DI service `<service id="\Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage" />` which allows simple key-value operations
* Added new class `\Shopware\Core\Framework\Adapter\Storage\MySQLKeyValueStorage` that is mysql implementation of `AbstractKeyValueStorage`, the data is stored in `app_config` table
