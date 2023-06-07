---
title: Fix cascade deletion of single custom primary key Association
issue: NEXT-28233
author: Dominik Wißler
author_email: wissla1993@aol.com
author_github: Dominik Wißler
---
# Core
* Changed method `fetchAssociation()` in `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityForeignKeyResolver` to skip flattening of single primary key arrays, if their `storageName` is not `'id'`.
