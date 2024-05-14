---
title: Improve CustomFieldSubscriber Performance
issue: NEXT-31349
---
# Core
* Changed `CustomFieldSubscriber` to use the MultiInsertQueryQueue, which improves performance.
* Added functionality to add `UpdateFieldOnDuplicateKey` to `MultiInsertQueryQueue`, to define fields which should be updated on duplicate keys.
