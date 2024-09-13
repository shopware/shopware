---
title: Fix self-referencing parents
issue: NEXT-36670
---

# Core
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityForeignKeyResolver` to not run into an infinite loop when a entity contains an association to itself.
* Added `\Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\ParentRelationValidator` to validate that no parent relation to itself can be created.
