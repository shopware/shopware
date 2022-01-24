---
title: Remove dot (`.`) syntax handling for associations
issue: NEXT-19034
---
# Core
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityReader::addAssociationFieldsToCriteria()` and removed obsolete code for dot syntax inside associations, which is not supported.
