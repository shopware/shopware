---
title: Fix custom fields with same names as foreign keys
issue: #12029
---
# Core
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue::hasUnresolvedForeignKey()` to not check JsonUpdate command payload for foreign key references, as JSON data can not include FK constraints, thus fixing an issue that custom fields with the same name as a FK field could not be saved.
