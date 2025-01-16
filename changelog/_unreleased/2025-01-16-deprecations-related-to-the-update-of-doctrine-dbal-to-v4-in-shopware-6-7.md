---
title: Deprecations related to the update to doctrine/dbal:4 in shopware 6.7
issue: NEXT-39353
author: Andrii Havryliuk
author_email: a.havryliuk@shopware.com
author_github: @Andrii Havryliuk
---
# Core
* Changed IteratorFactory to pass \Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder instead of Doctrine\DBAL\Query\QueryBuilder into LastIdQuery and OffsetQuery

___
# Next Major Version Changes
## OffsetQuery & LastIdQuery signature changes
OffsetQuery && LastIdQuery now accept \Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder instead of Doctrine\DBAL\Query\QueryBuilder.
If you are creating those classes manually, you need to change creating code:
```php
$queryBuilder = $this->connection->createQueryBuilder();
$lastIdQuery = new LastIdQuery($query);
$offsetQuery = new OffsetQuery($query);
```
to
``` php
$queryBuilder = new \Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder($this->connection);
$lastIdQuery = new LastIdQuery($queryBuilder);
$offsetQuery = new OffsetQuery($queryBuilder);
```
