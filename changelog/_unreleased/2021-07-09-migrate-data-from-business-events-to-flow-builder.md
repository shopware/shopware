---
title: Migrate data from Business events to Flow builder
issue: NEXT-15106
---
# Core
* Added new table `sales_channel_rule`.
* Added new migration `Migration1625583596CreateActionEventFlowMigrateTable`.
* Added new migration `Migration1625583619MoveDataFromEventActionToFlow` to migrate data from Business events to Flow builder.
* Added `SequenceTreeBuilder` class at `Shopware\Core\Content\Flow\SequenceTree`.
