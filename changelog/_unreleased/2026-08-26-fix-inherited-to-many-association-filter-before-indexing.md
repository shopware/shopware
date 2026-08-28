---
title: Fix filtering inherited to-many associations before the entity indexer ran
issue: 19790
---
# Core
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\OneToManyAssociationFieldResolver::getSourceColumn()` to fall back to the entity's own id when the inheritance column is still `NULL`. The column is written by the entity indexer, which runs asynchronously, so criteria filtering an inherited to-many association (for example `visibilities.salesChannelId` on `product`) silently returned no results until indexing had caught up.
