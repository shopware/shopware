---
title: Fix DAL inherited to-many field reads with limits
issue: 5328
---
# Core
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityReader::loadManyToManyWithCriteria` to filter by the fetched mapping ids for inherited association, instead of the primary key directly.

